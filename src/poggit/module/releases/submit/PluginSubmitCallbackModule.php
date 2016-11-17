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

namespace poggit\module\releases\submit;

use poggit\exception\GitHubAPIException;
use poggit\model\PluginRelease;
use poggit\module\Module;
use poggit\Poggit;
use poggit\session\SessionUtils;

class PluginSubmitCallbackModule extends Module {

    public function getName(): string {
        return "release.submit.callback";
    }

    public function output() {
        $session = SessionUtils::getInstance();
        if(!$session->isLoggedIn()) $this->errorAccessDenied();
        foreach(["owner", "repo", "project", "build", "antiForge",
                    "name", "shortDesc", "version", "isPreRelease"] as $field) {
            if(!isset($_POST[$field])) $this->errorBadRequest("Missing POST field $field");
        }
        if($_POST["antiForge"] !== $session->getAntiForge()) $this->errorAccessDenied();

        $owner = $_POST["owner"];
        $repo = $_POST["repo"];

        try {
            $repoInfo = Poggit::ghApiGet("repos/$owner/$repo", $session->getAccessToken());
        } catch(GitHubAPIException$e) {
            $this->errorAccessDenied("The repo does not exist!");
            return;
        }
        if(!$repoInfo->permissions->admin) {
            $this->errorAccessDenied("You must have admin access to a repo to release it. " .
                "Your current access: " . str_replace("\n", ", ", yaml_emit($repoInfo->permissions)));
        }

        $project = $_POST["project"];
        $build = (int) $_POST["build"];

        $rows = Poggit::queryAndFetch("SELECT p.projectId, b.buildId, b.cause FROM builds b 
            INNER JOIN projects p ON b.projectId = p.projectId WHERE p.repoId = ? AND p.name = ? AND b.class = ? AND b.internal = ?",
            "isii", $repoInfo->id, $project, Poggit::BUILD_CLASS_DEV, $build);
        if(count($rows) === 0) $this->errorAccessDenied("The build does not exist!");

        $release = new PluginRelease();
        // TODO populate $release
    }
}
