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

    public function __construct(string $user, string $repo, string $project) {
        $this->user = $user;
        $this->repoName = $repo;
        $this->projectName = $project;

        $session = SessionUtils::getInstance();
        $token = $session->getAccessToken();
        try {
            $this->repo = Poggit::ghApiGet("repos/$user/$repo", $token);
        } catch(GitHubAPIException $e) {
            $name = htmlspecialchars($session->getLogin()["name"]);
            $repoNameHtml = htmlspecialchars($user . "/" . $repo);
            throw new AltVariantException(new RecentBuildPageVariant(<<<EOD
<p>The repo $repoNameHtml does not exist or is not accessible to your GitHub account (<a href="$name"?>@$name</a>).</p>
EOD
            ));
        }
        $project = Poggit::queryAndFetch("SELECT
            r.private, p.type, p.name, p.framework, p.lang, p.projectId, p.path
            FROM projects p INNER JOIN repos r ON p.repoId=r.repoId
            WHERE r.build = 1 AND r.owner = ? AND r.name = ? AND p.name = ?", "sss", $this->user, $this->repoName, $this->projectName);
        if(count($project) === 0) {
            throw new AltVariantException(new RecentBuildPageVariant(<<<EOD
<p>Such project does not exist, or the repo does not have Poggit Build enabled.</p>
EOD
            ));
        }
        $this->project = $project[0];
        $this->project["private"] = (bool) (int) $this->project["private"];
        $this->project["type"] = (int) $this->project["type"];
        $this->project["lang"] = (bool) (int) $this->project["lang"];
        $this->project["projectId"] = (int) $this->project["projectId"];
    }

    public function getTitle() : string {
        return htmlspecialchars("$this->projectName ($this->user/$this->repoName)");
    }

    public function output() {
        ?>
        <script>
            var projectData = {
                owner: <?= json_encode($this->repo->owner->login) ?>,
                name: <?= json_encode($this->repo->name) ?>,
                project: <?= json_encode($this->project["name"]) ?>
            };
        </script>
        <h1>
            <?= Poggit::$PROJECT_TYPE_HUMAN[$this->project["type"]] ?> project:
            <a href="<?= Poggit::getRootPath() ?>build/<?= $this->repo->full_name ?>/<?= urlencode(
                $this->project["name"]) ?>">
                <?= htmlspecialchars($this->project["name"]) ?>
            </a>
            <?php if($this->repo->private) { ?>
                <img title="This is a private repo" width="16"
                     src="https://maxcdn.icons8.com/Android_L/PNG/24/Very_Basic/lock-24.png">
            <?php } ?>
            <?php Poggit::ghLink($this->repo->html_url . "/" . "tree/" . $this->repo->default_branch . "/" . $this->project["path"]) ?>
        </h1>
        <p>From repo:
            <a href="<?= Poggit::getRootPath() ?>build/<?= $this->repo->owner->login ?>">
                <?= $this->repo->owner->login ?></a> <?php Poggit::ghLink($this->repo->owner->html_url) ?> /
            <a href="<?= Poggit::getRootPath() ?>build/<?= $this->repo->full_name ?>">
                <?= $this->repo->name ?></a> <?php Poggit::ghLink($this->repo->html_url) ?></p>
        <p><input type="checkbox" <?= $this->project["lang"] ? "checked" : "" ?> disabled> PogLang translation manager
        </p>
        <p>Model: <input type="text" value="<?= $this->project["framework"] ?>" disabled></p>
        <h2>Build history</h2>
        <table id="project-build-history" class="info-table">
            <tr>
                <th>Type</th>
                <th>Build #</th>
                <th>Branch</th>
                <th>Cause</th>
                <th>Date</th>
                <th>Build &amp;</th>
                <th>Download</th>
                <th>Lint</th>
            </tr>
        </table>
        <a class="action" onclick="loadMoreHistory(<?= $this->project["projectId"] ?>)">Load more build history</a>
        <script>
            loadMoreHistory(<?= $this->project["projectId"] ?>);
        </script>
        <?php
    }
}
