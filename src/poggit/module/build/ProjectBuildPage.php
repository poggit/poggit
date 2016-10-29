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
use poggit\model\ReleaseConstants;
use poggit\module\VarPage;
use poggit\Poggit;
use poggit\session\SessionUtils;

class ProjectBuildPage extends VarPage {
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
    /** @var array */
    private $latestBuild;
    /** @var array|null */
    private $release, $preRelease;

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
            throw new RecentBuildPage(<<<EOD
<p>The repo $repoNameHtml does not exist or is not accessible to your GitHub account (<a href="$name"?>@$name</a>).</p>
EOD
            );
        }
        $project = Poggit::queryAndFetch("SELECT r.private, p.type, p.name, p.framework, p.lang, p.projectId, p.path,
            (SELECT CONCAT_WS(':', b.class, b.internal) FROM builds b WHERE p.projectId = b.projectId AND b.class != ? ORDER BY created DESC LIMIT 1) AS latestBuild
            FROM projects p INNER JOIN repos r ON p.repoId = r.repoId
            WHERE r.build = 1 AND r.owner = ? AND r.name = ? AND p.name = ?", "isss", Poggit::BUILD_CLASS_PR, $this->user, $this->repoName, $this->projectName);
        if(count($project) === 0) {
            throw new RecentBuildPage(<<<EOD
<p>Such project does not exist, or the repo does not have Poggit CI enabled.</p>
EOD
            );
        }
        $this->project = $project[0];
        $this->project["private"] = (bool) (int) $this->project["private"];
        $this->project["type"] = (int) $this->project["type"];
        $this->project["lang"] = (bool) (int) $this->project["lang"];
        $this->latestBuild = explode(":", $this->project["latestBuild"], 2);
        $this->latestBuild[0] = Poggit::$BUILD_CLASS_IDEN[$this->latestBuild[0]];
        $projectId = $this->project["projectId"] = (int) $this->project["projectId"];

        $latestRelease = Poggit::queryAndFetch("SELECT name, releaseId, version, releases.type, icon, art.dlCount,
            (SELECT COUNT(*) FROM releases ra WHERE ra.projectId = releases.projectId) AS releaseCnt
             FROM releases INNER JOIN resources art ON releases.artifact = art.resourceId
             WHERE projectId = ? ORDER BY creation DESC LIMIT 1", "i", $projectId);
        if(count($latestRelease) !== 0) {
            $latestRelease = $latestRelease[0];
            $latestRelease["releaseId"] = (int) $latestRelease["releaseId"];
            $type = $latestRelease["type"] = (int) $latestRelease["type"];
            $latestRelease["icon"] = (int) $latestRelease["icon"];
            $latestRelease["releaseCnt"] = (int) $latestRelease["releaseCnt"];
            $latestRelease["dlCount"] = (int) $latestRelease["dlCount"];

            if($type === ReleaseConstants::RELEASE_TYPE_PRE_RELEASE) {
                $this->preRelease = $latestRelease;
                $latestRelease = Poggit::queryAndFetch("SELECT name, releaseId, version, releases.type, icon,
                    (SELECT COUNT(*) FROM releases ra WHERE ra.projectId = releases.projectId) AS releaseCnt
                     FROM releases WHERE projectId = ? ORDER BY creation DESC LIMIT 1", "i", $projectId);
                if(count($latestRelease) !== 0) {
                    $latestRelease = $latestRelease[0];
                    $latestRelease["releaseId"] = (int) $latestRelease["releaseId"];
                    $latestRelease["type"] = (int) $latestRelease["type"];
                    $latestRelease["icon"] = (int) $latestRelease["icon"];
                    $latestRelease["releaseCnt"] = (int) $latestRelease["releaseCnt"];
                    $latestRelease["dlCount"] = (int) $latestRelease["dlCount"];
                    $this->release = $latestRelease;
                } else $this->release = null;
            } else {
                $this->release = $latestRelease;
                $this->preRelease = null;
            }
        } else $this->release = $this->preRelease = null;
    }

    public function getTitle() : string {
        return htmlspecialchars("$this->projectName ($this->user/$this->repoName)");
    }

    public function output() {
        ?>
        <!--suppress JSUnusedLocalSymbols -->
        <script>
            var projectData = {
                owner: <?= json_encode($this->repo->owner->login) ?>,
                name: <?= json_encode($this->repo->name) ?>,
                project: <?= json_encode($this->project["name"]) ?>
            };
        </script>
        <h1>
            <?= Poggit::$PROJECT_TYPE_HUMAN[$this->project["type"]] ?> project:
            <a href="<?= Poggit::getRootPath() ?>ci/<?= $this->repo->full_name ?>/<?= urlencode(
                $this->project["name"]) ?>">
                <?= htmlspecialchars($this->project["name"]) ?>
            </a>
            <?php if($this->repo->private) { ?>
                <img title="This is a private repo" width="16"
                     src="https://maxcdn.icons8.com/Android_L/PNG/24/Very_Basic/lock-24.png">
            <?php } ?>
            <?php Poggit::ghLink($this->repo->html_url . "/tree/" . $this->repo->default_branch . "/" . $this->project["path"]) ?>
        </h1>
        <p>From repo:
            <a href="<?= Poggit::getRootPath() ?>ci/<?= $this->repo->owner->login ?>">
                <?php Poggit::displayUser($this->repo->owner) ?></a> /
            <a href="<?= Poggit::getRootPath() ?>ci/<?= $this->repo->full_name ?>">
                <?= $this->repo->name ?></a> <?php Poggit::ghLink($this->repo->html_url) ?></p>
        <p><input type="checkbox" <?= $this->project["lang"] ? "checked" : "" ?> disabled> PogLang Translate</p>
        <p>Model: <input type="text" value="<?= $this->project["framework"] ?>" disabled></p>
        <?php
        if($this->repo->permissions->admin) {
            ?>
            <h2>Poggit Release <?php Poggit::displayAnchor("releases") ?></h2>
            <?php
            $action = $moduleName = "update";
            if($this->release === null and $this->preRelease === null) {
                $action = "release";
                $moduleName = "submit";
                ?>
                <p>This plugin has not been released yet.</p>
                <?php
            } elseif($this->release === null and $this->preRelease !== null) { // no releases yet
                echo '<h3>Latest pre-release';
                $this->showRelease($this->preRelease);
            } elseif($this->release !== null) {
                if($this->preRelease !== null) {
                    echo '<h3>Latest pre-release';
                    $this->showRelease($this->preRelease);
                }
                echo '<h3>Latest release</h3>';
                $this->showRelease($this->release);
            }
            ?>
            <form id="submitProjectForm" method="post"
                  action="<?= Poggit::getRootPath() ?><?= $moduleName ?>/<?= $this->user ?>/<?= $this->repoName ?>/<?= $this->projectName ?>/<?= implode("/", $this->latestBuild) ?>">
                <input type="hidden" name="readRules"
                       value="<?= ($this->release === null and $this->preRelease === null) ? "off" : "on" ?>">
                <p><span class="action" onclick='document.getElementById("submitProjectForm").submit()'>
                    Click this button to submit the latest non-PR build for <?= $action ?>.
                </span></p>
            </form>
        <?php } ?>
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

    private function showRelease(array $release) {
        ?>
        <p>Name:
            <img src="<?= Poggit::getRootPath() ?>r/<?= $release["icon"] ?>" height="32">
            <strong><a
                    href="<?= Poggit::getRootPath() ?>rel/<?= urlencode($release["name"]) ?>">
                    <?= htmlspecialchars($release["name"]) ?></a></strong>.
            <!-- TODO probably need to support identical names? -->
        </p>
        <p>Version: <?= $release["version"] ?> (<?= $release["releaseCnt"] ?> update)</p>
        <p>Version downloads: <?= $release["version"] ?></p>
        <?php
    }

    public function og() {
        echo "<meta property='article:author' content='$this->user'/>";
        echo "<meta property='article:section' content='CI'/>";
        return "article";
    }

    public function getMetaDescription() : string {
        return "Builds in $this->projectName in $this->user/$this->repoName by Poggit-CI";
    }
}

// TODO add button for migration of projects from repo to repo
