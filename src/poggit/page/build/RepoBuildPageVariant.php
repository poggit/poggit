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

class RepoBuildPageVariant extends BuildPageVariant {
    /** @var string */
    private $user;
    /** @var string */
    private $repoName;
    /** @var \stdClass */
    private $repo;
    /** @var array */
    private $projects;
    /** @var array[] */
    private $builds;
    /** @var bool */
    private $private;

    public function __construct(BuildPage $page, string $user, string $repo) {
        $this->user = $user;
        $this->repoName = $repo;
        $session = SessionUtils::getInstance();
        $token = $session->isLoggedIn() ? $session->getLogin()["access_token"] : "";
        $repoNameHtml = htmlspecialchars("$user/$repo");
        try {
            $this->repo = $repo = Poggit::ghApiGet("repos/$user/$repo", $token);
        } catch(GitHubAPIException $e) {
            $name = htmlspecialchars($session->getLogin()["name"]);
            throw new AltVariantException(new RecentBuildPageVariant($page, <<<EOD
<p>The repo $repoNameHtml does not exist or is not accessible to your GitHub account (<a href="$name"?>@$name</a>).</p>
EOD
            ));
        }
        $repoRow = Poggit::queryAndFetch("SELECT private, build FROM repos WHERE repoId = $repo->id");
        if(count($repoRow) === 0 or !((int) $repoRow[0]["build"])) {
            throw new AltVariantException(new RecentBuildPageVariant($page, <<<EOD
<p>The repo $repoNameHtml does not have Poggit Build enabled.</p>
EOD
            ));
        }
        $this->private = (bool) (int) $repoRow[0]["private"];
        $this->projects = Poggit::queryAndFetch("SELECT projectId, name, type, framework, lang FROM projects WHERE repoId = $repo->id");
        if(count($this->projects) === 0) {
            throw new AltVariantException(new RecentBuildPageVariant($page, <<<EOD
<p>The repo $repoNameHtml does not have any projects.</p>
EOD
            ));
        }
        Poggit::queryAndFetch("SET @currvalue = NULL, @currcount = NULL");
        foreach(Poggit::queryAndFetch("SELECT buildId, internal, projectId, resourceId, unix_timestamp(created) AS creation FROM
                (SELECT b.buildId, b.internal, b.projectId, b.resourceId, b.created,
                    @currcount := IF(@currvalue = b.projectId, @currcount + 1, 1) AS ord,
                    @currvalue := b.projectId
                FROM builds b INNER JOIN projects p ON b.projectId = p.projectId
                WHERE p.repoId = $repo->id
            ORDER BY b.projectId, created DESC) AS t WHERE ord <= 2") as $build) {
            $this->builds[$build["projectId"]][] = $build;
        }
        Poggit::getLog()->d(json_encode($this->builds));
    }

    public function getTitle() : string {
        return "Projects in {$this->repo->owner->login}/{$this->repo->name}";
    }

    public function output() { ?>
        <h1>Projects in <?= $this->repo->owner->login ?> <?php Poggit::ghLink($this->repo->owner->html_url) ?>
            / <?= $this->repo->name ?> <?php Poggit::ghLink($this->repo->html_url) ?>
            <?php if($this->private) { ?>
                <img title="This is a private repo" width="16"
                     src="https://maxcdn.icons8.com/Android_L/PNG/24/Very_Basic/lock-24.png">
            <?php } ?>
        </h1>
        <?php foreach($this->projects as $project) {
            $pname = $project["name"]; ?>
            <div class="project-const">
                <h2>
                    <?= Poggit::$PROJECT_TYPE_HUMAN[$project["type"]] ?> project:
                    <a href="<?= Poggit::getRootPath() ?>build/<?= $this->repo->full_name ?>/<?= urlencode($pname) ?>">
                        <?= htmlspecialchars($pname) ?>
                    </a>
                </h2>
                <h3>Settings</h3>
                <input type="checkbox" class="check-lang" disabled
                    <?php if((int) $project["lang"]) echo "checked"; ?>
                > Poggit translation manager
                <p>Plugin model:
                    <input type="text" disabled value="<?= htmlspecialchars($project["framework"]) ?>"></p>
                <h3>Latest Builds</h3>
                <ul>
                    <?php foreach($this->builds[$project["projectId"]] as $build) {
                        $resId = (int) $build["resourceId"]; ?>
                        <li>Build
                            <?php Poggit::showBuildNumbers($build["buildId"], $build["internal"],
                                "build/{$this->repo->full_name}/" . urlencode($pname) . "/" . $build["internal"]) ?>:
                            <a href="<?= Poggit::getRootPath() ?>r/<?= $resId ?>/<?= $pname ?>.phar?cookie">
                                Direct download link</a>
                            (<a onclick='promptDownloadResource(<?= $resId ?>,
                            <?= json_encode($pname . ".phar", JSON_UNESCAPED_SLASHES) ?>);' href="#"
                            >Download with custom filename</a>)
                            <?php if($this->private) { ?>
                                <br>
                                <img title="This is a private repo" width="16"
                                     src="https://maxcdn.icons8.com/Android_L/PNG/24/Very_Basic/lock-24.png">
                                This is a private repo. You must provide a GitHub access token if you download this
                                plugin without browser (e.g. through <code>curl</code> or <code>wget</code>). See
                                <a href="<?= Poggit::getRootPath() ?>help.resource.private">this article</a> for
                                details.
                            <?php } ?>
                            <br>
                            Created: <span class="time" data-timestamp="<?= $build["creation"] ?>"></span>
                        </li>
                    <?php } ?>
                </ul>
            </div>
        <?php } ?>
        <?php
    }
}
