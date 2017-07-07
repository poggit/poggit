<?php

/*
 * Poggit
 *
 * Copyright (C) 2016-2017 Poggit
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

use poggit\account\Session;
use poggit\ci\builder\ProjectBuilder;
use poggit\Meta;
use poggit\module\Module;
use poggit\utils\internet\Curl;
use poggit\utils\internet\GitHubAPIException;
use poggit\utils\internet\Mysql;

class AbsoluteBuildIdModule extends Module {
    public function getName(): string {
        return "babs";
    }

    public function output() {
        $id = hexdec($this->getQuery());
        $builds = Mysql::query(
            "SELECT builds.class, builds.internal, projects.repoId, repos.owner, repos.name, projects.name AS pname
            FROM builds INNER JOIN projects ON builds.projectId = projects.projectId
            INNER JOIN repos ON projects.repoId = repos.repoId
            WHERE builds.buildId = ? AND builds.class IS NOT NULL", "i", $id);
        if(!isset($builds[0])) {
            $this->errorNotFound();
        }
        $build = $builds[0];
        $session = Session::getInstance();
        try {
            $repo = Curl::ghApiGet("repositories/" . $build["repoId"], $session->getAccessToken());
        } catch(GitHubAPIException $e) {
            $this->errorNotFound();
            return;
        }
        $classes = [
            ProjectBuilder::BUILD_CLASS_DEV => "dev",
            ProjectBuilder::BUILD_CLASS_PR => "pr"
        ];
        Meta::redirect("ci/" . $repo->full_name . "/" . ($build["pname"] === $repo->name ? "~" : $build["pname"]) . "/" . $classes[$build["class"]] . ":" . $build["internal"]);
    }
}
