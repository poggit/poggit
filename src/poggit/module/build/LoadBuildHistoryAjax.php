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

namespace poggit\module\build;

use poggit\module\ajax\AjaxModule;
use poggit\Poggit;

class LoadBuildHistoryAjax extends AjaxModule {
    protected function impl() {
        if(!isset($_REQUEST["projectId"])) $this->errorBadRequest("Missing parameter 'projectId'");
        $projectId = (int) $_REQUEST["projectId"];
        $start = (int) ($_REQUEST["start"] ?? 0x7FFFFFFF);
        $count = (int) ($_REQUEST["count"] ?? 5);
        $builds = Poggit::queryAndFetch("SELECT
            b.buildId, b.resourceId, b.class, b.branch, b.cause, b.internal, unix_timestamp(b.created) AS creation,
            b.status, r.owner AS repoOwner, r.name AS repoName, p.name AS projectName
            FROM builds b INNER JOIN projects p ON b.projectId=p.projectId
            INNER JOIN repos r ON p.repoId=r.repoId
            WHERE b.projectId = ? AND b.class IS NOT NULL AND b.internal < ?
            ORDER BY b.internal DESC LIMIT $count",
            "ii", $projectId, $start);
        foreach($builds as &$build) {
            $build["buildId"] = (int) $build["buildId"];
            $build["resourceId"] = (int) $build["resourceId"];
            $build["class"] = (int) $build["class"];
            $build["classString"] = Poggit::$BUILD_CLASS_HUMAN[$build["class"]];
            $build["internal"] = (int) $build["internal"];
            $build["creation"] = (int) $build["creation"];
        }
        echo json_encode([
            "builds" => $builds
        ]);
    }

    public function getName() : string {
        return "build.history";
    }

    protected function needLogin() : bool {
        return false;
    }
}
