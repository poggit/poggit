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
use poggit\module\VarPage;
use poggit\Poggit;
use poggit\session\SessionUtils;

class RepoBuildPage extends VarPage {
    /** @var string */
    private $user;
    /** @var string */
    private $repoName;
    /** @var \stdClass */
    private $repo;
    /** @var array */
    private $projects;
    /** @var array[] */
    private $builds = [];
    /** @var bool */
    private $private;

    public function __construct(string $user, string $repo) {
        $this->user = $user;
        $this->repoName = $repo;
        $session = SessionUtils::getInstance();
        $token = $session->getAccessToken();
        $repoNameHtml = htmlspecialchars("$user/$repo");
        try {
            $this->repo = $repo = Poggit::ghApiGet("repos/$user/$repo", $token);
        } catch(GitHubAPIException $e) {
            $name = htmlspecialchars($session->getLogin()["name"]);
            throw new RecentBuildPage(<<<EOD
<p>The repo $repoNameHtml does not exist or is not accessible to your GitHub account (<a href="$name"?>@$name</a>).</p>
EOD
            );
        }
        $repoRow = Poggit::queryAndFetch("SELECT private, build FROM repos WHERE repoId = $repo->id");
        if(count($repoRow) === 0 or !((int) $repoRow[0]["build"])) {
            throw new RecentBuildPage(<<<EOD
<p>The repo $repoNameHtml does not have Poggit CI enabled.</p>
EOD
            );
        }
        $this->private = (bool) (int) $repoRow[0]["private"];
        $this->projects = Poggit::queryAndFetch("SELECT projectId, name, path, type, framework, lang FROM projects WHERE repoId = $repo->id");
        if(count($this->projects) === 0) {
            throw new RecentBuildPage(<<<EOD
<p>The repo $repoNameHtml does not have any projects yet.</p>
EOD
            );
        }
        Poggit::queryAndFetch("SET @currvalue = NULL, @currcount = NULL");
        foreach(Poggit::queryAndFetch("SELECT buildId, class, internal, projectId, resourceId, unix_timestamp(created) AS creation FROM
                (SELECT b.buildId, b.class, b.internal, b.projectId, b.resourceId, b.created,
                    @currcount := IF(@currvalue = b.projectId, @currcount + 1, 1) AS ord,
                    @currvalue := b.projectId
                FROM builds b INNER JOIN projects p ON b.projectId = p.projectId
                WHERE p.repoId = ? AND b.class IS NOT NULL
            ORDER BY b.projectId, created DESC) AS t WHERE ord <= 2", "i", $repo->id) as $build) {
            $this->builds[$build["projectId"]][] = $build;
        }
    }

    public function getTitle() : string {
        return "Projects in {$this->repo->owner->login}/{$this->repo->name}";
    }

    public function output() { ?>
        <h1>Projects in
            <?php Poggit::displayRepo($this->repo->owner->login, $this->repo->name, $this->repo->owner->avatar_url) ?>
            <?php if($this->private) { ?>
                <img title="This is a private repo" width="16"
                     src="https://maxcdn.icons8.com/Android_L/PNG/24/Very_Basic/lock-24.png">
            <?php } ?>
        </h1>
        <?php foreach($this->projects as $project) {
            $pname = $project["name"]; ?>
            <div class="brief-info">
                <h2>
                    <?= Poggit::$PROJECT_TYPE_HUMAN[$project["type"]] ?> project:
                    <a href="<?= Poggit::getRootPath() ?>ci/<?= $this->repo->full_name ?>/<?= urlencode($pname) ?>">
                        <?= htmlspecialchars($pname) ?>
                    </a>
                    <?php Poggit::ghLink($this->repo->html_url . "/" . "tree/" . $this->repo->default_branch . "/" . $project["path"]) ?>
                </h2>
                <!--                <h3>Settings</h3>-->
                <!--                <input type="checkbox" class="check-lang" disabled-->
                <!--                    -->
                <?php //if((int) $project["lang"]) echo "checked"; ?><!-- > PogLang translation manager-->
                <!--                <p>Plugin model:-->
                <!--                    <input type="text" disabled value="-->
                <?php //= htmlspecialchars($project["framework"]) ?><!--"></p>-->
                <h3>Latest Builds</h3>
                <ul>
                    <?php foreach($this->builds[$project["projectId"]] as $build) {
                        $resId = (int) $build["resourceId"]; ?>
                        <li><?= Poggit::$BUILD_CLASS_HUMAN[$build["class"]] ?> build
                            <?php
                            Poggit::showBuildNumbers($build["buildId"], $build["internal"],
                                "ci/{$this->repo->full_name}/" . urlencode($pname) . "/" .
                                Poggit::$BUILD_CLASS_IDEN[$build["class"]] . ":" . $build["internal"])
                            ?>:
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

    public function getMetaDescription() : string {
        return "Projects in {$this->repo->full_name} built by Poggit";
    }
}
// TODO allow deleting projects
