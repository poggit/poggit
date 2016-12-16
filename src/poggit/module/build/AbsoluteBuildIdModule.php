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

use poggit\builder\ProjectBuilder;
use poggit\module\Module;
use poggit\Poggit;
use poggit\utils\internet\CurlUtils;
use poggit\utils\internet\GitHubAPIException;
use poggit\utils\internet\MysqlUtils;
use poggit\utils\SessionUtils;

class AbsoluteBuildIdModule extends Module {
    public function getName(): string {
        return "babs";
    }

    public function output() {
        $id = hexdec($this->getQuery());
        $builds = MysqlUtils::query(
            "SELECT builds.class, builds.internal, projects.repoId, repos.owner, repos.name, projects.name AS pname
            FROM builds INNER JOIN projects ON builds.projectId = projects.projectId
            INNER JOIN repos ON projects.repoId = repos.repoId
            WHERE builds.buildId = ? AND builds.class IS NOT NULL", "i", $id);
        if(!isset($builds[0])) {
            $this->errorNotFound();
        }
        $build = $builds[0];
        $session = SessionUtils::getInstance();
        try {
            $repo = CurlUtils::ghApiGet("repositories/" . $build["repoId"], $session->getAccessToken());
        } catch(GitHubAPIException $e) {
            $this->errorNotFound();
            return;
        }
        $classes = [
            ProjectBuilder::BUILD_CLASS_DEV => "dev",
//            ProjectBuilder::BUILD_CLASS_BETA => "beta",
//            ProjectBuilder::BUILD_CLASS_RELEASE => "rc",
            ProjectBuilder::BUILD_CLASS_PR => "pr"
        ];
        Poggit::redirect("ci/" . $repo->full_name . "/" . $build["pname"] . "/" . $classes[$build["class"]] . ":" . $build["internal"]);
    }
}
