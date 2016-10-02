<?php

/*
 * Copyright 2016 poggit
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

namespace poggit\page\build;

use poggit\exception\GitHubAPIException;
use poggit\Poggit;
use poggit\session\SessionUtils;

class ProjectBuildPageVariant extends BuildPageVariant {
    /** @var string */
    private $user;
    /** @var string */
    private $repoName;
    /** @var string */
    private $projectName;

    /** @var \stdClass */
    private $repo;
    /** @var array */
    private $project;
    /** @var array[] */
    private $builds;

    public function __construct(BuildPage $page, string $user, string $repo, string $project) {
        $this->user = $user;
        $this->repoName = $repo;
        $this->projectName = $project;

        $session = SessionUtils::getInstance();
        $token = $session->isLoggedIn() ? $session->getLogin()["access_token"] : "";
        $repoNameHtml = htmlspecialchars($user . "/" . $repo);
        try {
            $this->repo = Poggit::ghApiGet("repos/$user/$repo", $token);
        } catch(GitHubAPIException $e) {
            $name = htmlspecialchars($session->getLogin()["name"]);
            throw new AltVariantException(new RecentBuildPageVariant($page, <<<EOD
<p>The repo $repoNameHtml does not exist or is not accessible to your GitHub account (<a href="$name"?>@$name</a>).</p>
EOD
            ));
        }
        $project = Poggit::queryAndFetch("SELECT r.private, p.type, p.framework, p.lang, p.projectId
            FROM projects p INNER JOIN repos r ON p.repoId=r.repoId
            WHERE r.build = 1 AND r.owner = ? AND r.name = ? AND p.name = ?", "sss", $this->user, $this->repoName, $this->projectName);
        if(count($project) === 0) {
            throw new AltVariantException(new RecentBuildPageVariant($page, <<<EOD
<p>Such project does not exist, or the repo does not have Poggit Build enabled.</p>
EOD
            ));
        }
        $this->project = $project[0];
        $this->project["private"] = (bool) (int) $this->project["private"];
        $this->project["type"] = (int) $this->project["type"];
        $this->project["lang"] = (bool) (int) $this->project["lang"];
        $this->project["projectId"] = (int) $this->project["projectId"];
        $this->builds = Poggit::queryAndFetch("SELECT
            buildId, resourceId, class, branch, head, internal, unix_timestamp(created) AS creation
            FROM builds WHERE projectId = ?", "i", (int) $this->project["projectId"]);
        foreach($this->builds as &$build) {
            $build["buildId"] = (int) $build["buildId"];
            $build["resourceId"] = (int) $build["resourceId"];
            $build["class"] = (int) $build["class"];
            $build["internal"] = (int) $build["internal"];
            $build["creation"] = (int) $build["creation"];
        }
    }

    public function getTitle() : string {
        return htmlspecialchars("$this->projectName ($this->user/$this->repoName)");
    }

    public function output() {
        ?>
        <h1><?= htmlspecialchars($this->projectName) ?></h1>

        <?php
    }
}
