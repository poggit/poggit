<?php

/*
 * Poggit
 *
 * Copyright (C) 2016-2018 Poggit
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

namespace poggit\ci\api;

use poggit\ci\builder\ProjectBuilder;
use poggit\Meta;
use poggit\module\AjaxModule;
use poggit\utils\internet\Mysql;
use function count;
use function htmlspecialchars;
use function implode;
use function json_encode;
use function preg_match;
use function strlen;
use function substr;

class SearchBuildAjax extends AjaxModule {
    private $projectResults = [];

    protected function impl() {
        // read post fields
        $search = $this->param("search", $_POST);
        if(!preg_match('%^[A-Za-z0-9_]{2,}$%', $search)) $this->errorBadRequest("Invalid search field 'search'");

        $searchString = "%{$search}%";
        foreach(Mysql::query("SELECT p.name AS projectName, r.owner AS repoOwner, r.name AS repoName, p.projectId AS projectId,
            p.type AS projectType, p.framework AS projectFramework
            FROM projects p INNER JOIN repos r ON p.repoId = r.repoId
            WHERE (r.name LIKE ? OR r.owner LIKE ? OR p.name LIKE ?) AND private = 0 AND r.build > 0 ORDER BY (SELECT MAX(created) FROM builds WHERE builds.projectId = p.projectId) DESC",
            "sss", $searchString, $searchString, $searchString) as $row) {
            $row = (object) $row;
            $projectId = $row->projectId = (int) $row->projectId;
            $row->projectType = ProjectBuilder::$PROJECT_TYPE_HUMAN[$row->projectType];
            $this->projectResults[$projectId] = $row;
        }
        $resultsHtml = [];
        if(isset($this->projectResults)) {
            foreach($this->projectResults as $project) {
                $projectPath = Meta::root() . "ci/$project->repoOwner/$project->repoName/$project->projectName";
                $truncatedName = htmlspecialchars(substr($project->projectName, 0, 14) . (strlen($project->projectName) > 14 ? "..." : ""));
                $resultsHtml[] = <<<EOS
<div class="search-info">
    <p class="recent-build-box">
        <a href="$projectPath">$truncatedName</a>
        <span class="remark">
            {$project->repoName} by {$project->repoOwner}<br />
            Type: {$project->projectType}
        </span>
     </p>
</div>
EOS;
            }
        }

        $html = '<div class="search-results-header"><h4>' . count($resultsHtml) . '  result' . (count($resultsHtml) !== 1 ? "s" : "") . ' for "' . $_POST["search"] . '"</h4></div><div class="search-results-list">';
        $html .= implode($resultsHtml);
        $html .= '</div>';
        echo json_encode([
            "html" => $html
        ]);
    }

    protected function needLogin(): bool {
        return false;
    }
}
