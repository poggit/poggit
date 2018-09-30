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

use poggit\module\Module;
use poggit\utils\internet\Mysql;
use function header;
use function json_encode;

class BuildInfoModule extends Module {
    public function output() {
        $owner = $this->param("owner");
        $repo = $this->param("repo");
        $sha = $this->param("sha");
        header("Content-Type: application/json");
        echo json_encode(Mysql::query("SELECT
                projects.name AS projectName, buildId, class, internal, branch, created, resourceId, buildsAfterThis
            FROM builds
                INNER JOIN projects ON builds.projectId = projects.projectId
                INNER JOIN repos ON projects.repoId = repos.repoId
            WHERE owner = ? AND repos.name = ? AND sha LIKE ? AND private = 0", "sss", $owner, $repo, $sha . "%"));
    }
}
