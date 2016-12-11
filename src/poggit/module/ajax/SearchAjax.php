<?php

/*
 * Poggit
 *
 * Copyright (C) 2016 Poggit
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace poggit\module\ajax;

use poggit\Poggit;
use poggit\utils\MysqlUtils;
use poggit\builder\ProjectBuilder;

class SearchAjax extends AjaxModule {

    private $buildResults;

    protected function impl() {
        // read post fields
        if (!isset($_POST["search"]) || !preg_match('%^[A-Za-z0-9_]{2,}$%', $_POST["search"])) 
            $this->errorBadRequest("Invalid search field 'search'");

        $searchstring = "%" . $_POST["search"] . "%";
        foreach (MysqlUtils::query("SELECT b.buildId, b.internal, b.class, UNIX_TIMESTAMP(b.created) AS created, 
            r.owner, r.name AS repoName, p.name AS projectName
            FROM builds b INNER JOIN projects p ON b.projectId = p.projectId INNER JOIN repos r ON p.repoId = r.repoId
            WHERE (r.name LIKE ? OR r.owner LIKE ?) AND private = 0 AND r.build > 0 ORDER BY created DESC LIMIT 20", "ss", $searchstring , $searchstring) as $row) {
            $row = (object) $row;
            $buildId = $row->buildId = (int) $row->buildId;
            $row->internal = (int) $row->internal;
            $row->class = (int) $row->class;
            $row->created = (int) $row->created;
            $this->buildResults[$buildId] = $row;
        }
        $resultshtml = "<div class='searchresultsheader'><h3>Search Results</h3></div>";
        if (isset($this->buildResults)) {
            foreach ($this->buildResults as $build) {

                $resultshtml .= "<div class='brief-info'>"
                        . "<p class='recentbuildbox'>"
                        . "<a href='" . Poggit::getRootPath() . "ci/" . $build->owner . "/" . $build->repoName . "'>"
                        . htmlspecialchars($build->projectName) . "</a>"
                        . "<span class='remark'>" . $build->owner . "/" . $build->repoName . "<br/>"
                        . ProjectBuilder::$BUILD_CLASS_HUMAN[$build->class] . " Build # " . $build->internal
                        . "<br/>"
                        . "Created <span class='time-elapse' data-timestamp='" . $build->created . "'></span> ago</span>"
                        . "</p>"
                        . "</div>";
            }
        }

        echo json_encode([
            "html" => $resultshtml
        ]);
    }

    public function getName(): string {
        return "search.ajax";
    }

}
