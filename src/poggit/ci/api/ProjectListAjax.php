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

declare(strict_types=1);

namespace poggit\ci\api;

use poggit\account\Session;
use poggit\module\AjaxModule;
use poggit\utils\internet\GitHub;
use poggit\utils\internet\Mysql;
use function array_keys;
use function count;
use function json_encode;
use function strtolower;

class ProjectListAjax extends AjaxModule {
    protected function impl() {
        $owner = $this->param("owner");
        $repos = [];
        $session = Session::getInstance();
        $token = $session->getAccessToken();
        $session->close();
        foreach(GitHub::listHisRepos($owner, $token,
            "id: databaseId 
            owner{ login }
            name 
            admin: viewerCanAdminister") as $repo) {
            if($repo->admin && strtolower($repo->owner->login) === strtolower($owner)) {
                $repo->projectsCount = 0;
                $repos[$repo->id] = $repo;
            }
        }
        if(count($repos) > 0) {
            foreach(Mysql::arrayQuery("SELECT projects.repoId, COUNT(*) projectsCount, IF(build, 1, 0) build FROM projects
                INNER JOIN repos ON projects.repoId = repos.repoId
            WHERE projects.repoId IN (%s) GROUP BY projects.repoId", ["i", array_keys($repos)]) as $row) {
                $repo = $repos[$row["repoId"]];
                $repo->projectsCount = (int) $row["projectsCount"];
                $repo->build = ((int) $row["build"]) === 1;
            }
        }
        echo json_encode($repos);
    }
}
