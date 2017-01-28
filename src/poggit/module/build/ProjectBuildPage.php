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

namespace poggit\module\build;

use poggit\builder\ProjectBuilder;
use poggit\embed\EmbedUtils;
use poggit\module\VarPage;
use poggit\Poggit;
use poggit\release\PluginRelease;
use poggit\utils\internet\CurlUtils;
use poggit\utils\internet\GitHubAPIException;
use poggit\utils\internet\MysqlUtils;
use poggit\utils\SessionUtils;

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
    /** @var bool */
    private $authorized;
    /** @var int */
    private $adminlevel = 0;
    /** @var array|null */
    private $release, $preRelease, $allReleases;

    public function __construct(string $user, string $repo, string $project) {
        $this->user = $user;
        $this->repoName = $repo;
        $this->projectName = $project === "~" ? $repo : $project;
        $this->authorized = false;
        $session = SessionUtils::getInstance();
        $this->adminlevel = Poggit::getAdmlv($session->getLogin()["name"] ?? "") ?? 0;
        $token = $session->getAccessToken();
        try {
            $this->repo = CurlUtils::ghApiGet("repos/$user/$repo", $token);
            $this->authorized = $session->isLoggedIn() && isset($this->repo->permissions) && $this->repo->permissions->admin == true;
        } catch(GitHubAPIException $e) {
            $name = htmlspecialchars($session->getLogin()["name"]);
            $repoNameHtml = htmlspecialchars($user . "/" . $repo);
            throw new RecentBuildPage(<<<EOD
<p>The repo $repoNameHtml does not exist or is not accessible to your GitHub account (<a href="$name"?>@$name</a>).</p>
EOD
            );
        }
        $project = MysqlUtils::query("SELECT r.private, p.type, p.name, p.framework, p.lang, p.projectId, p.path,
            (SELECT CONCAT_WS(':', b.class, b.internal) FROM builds b WHERE p.projectId = b.projectId AND b.class != ? ORDER BY created DESC LIMIT 1) AS latestBuild
            FROM projects p INNER JOIN repos r ON p.repoId = r.repoId
            WHERE r.build = 1 AND r.owner = ? AND r.name = ? AND p.name = ?", "isss", ProjectBuilder::BUILD_CLASS_PR, $this->user, $this->repoName, $this->projectName);
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
        $this->latestBuild[0] = ProjectBuilder::$BUILD_CLASS_IDEN[$this->latestBuild[0]];
        $projectId = $this->project["projectId"] = (int) $this->project["projectId"];

        $allReleases = MysqlUtils::query("SELECT name, releaseId, releases.buildId, b.internal, b.class, state, version, releases.flags, icon, art.dlCount,
            (SELECT COUNT(*) FROM releases ra WHERE ra.projectId = releases.projectId) AS releaseCnt
             FROM releases
             INNER JOIN resources art ON releases.artifact = art.resourceId
             INNER JOIN builds b ON b.buildId = releases.buildId
             WHERE releases.projectId = ? ORDER BY state DESC", "i", $projectId);
        if(count($allReleases) !== 0) {
            $this->allReleases = $allReleases;
            $latestRelease = $allReleases[0];
            $latestRelease["releaseId"] = (int) $latestRelease["releaseId"];
            $flags = $latestRelease["flags"] = (int) $latestRelease["flags"];
            $latestRelease["releaseCnt"] = (int) $latestRelease["releaseCnt"];
            $latestRelease["dlCount"] = (int) $latestRelease["dlCount"];
            $latestRelease["buildId"] = (int) $latestRelease["buildId"];
            $latestRelease["internal"] = (int) $latestRelease["internal"];
            $latestRelease["state"] = (int) $latestRelease["state"];

            if($flags & PluginRelease::RELEASE_FLAG_PRE_RELEASE) {
                $this->preRelease = $latestRelease;
                $latestRelease = MysqlUtils::query("SELECT name, releaseId, version, icon, art.dlCount, b.internal, b.class, state,
                    (SELECT COUNT(*) FROM releases ra WHERE ra.projectId = releases.projectId AND ra.creation <= releases.creation) AS releaseCnt
                    FROM releases
                    INNER JOIN builds b ON b.buildId = releases.buildId
                    INNER JOIN resources art ON releases.artifact = art.resourceId
                    WHERE releases.projectId = ? AND (flags & ?) = 0 ORDER BY state DESC LIMIT 1", "ii", $projectId, PluginRelease::RELEASE_FLAG_PRE_RELEASE);
                if(count($latestRelease) !== 0) {
                    $latestRelease = $latestRelease[0];
                    $latestRelease["releaseId"] = (int) $latestRelease["releaseId"];
                    $latestRelease["releaseCnt"] = (int) $latestRelease["releaseCnt"];
                    $latestRelease["dlCount"] = (int) $latestRelease["dlCount"];
                    $latestRelease["internal"] = (int) $latestRelease["internal"];
                    $latestRelease["state"] = (int) $latestRelease["state"];
                    $this->release = $latestRelease;
                } else $this->release = null;
            } else {
                $this->release = $latestRelease;
                $this->preRelease = null;
            }
        } else $this->release = $this->preRelease = null;
    }

    public function getTitle(): string {
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
        <div>
            <h1>
                <?= ProjectBuilder::$PROJECT_TYPE_HUMAN[$this->project["type"]] ?> project:
                <a href="<?= Poggit::getRootPath() ?>ci/<?= $this->repo->full_name ?>/<?= urlencode(
                    $this->project["name"]) ?>">
                    <?= htmlspecialchars($this->project["name"]) ?>
                </a>
                <?php if($this->repo->private) { ?>
                    <img title="This is a private repo" width="16"
                         src="https://maxcdn.icons8.com/Android_L/PNG/24/Very_Basic/lock-24.png"/>
                <?php } ?>
                <?php EmbedUtils::ghLink($this->repo->html_url . "/tree/" . $this->repo->default_branch . "/" . $this->project["path"]) ?>
                <span style="cursor: pointer;" onclick="$('#badgeDialog').dialog('open')">
                <?php
                $projectUrl = Poggit::getSecret("meta.extPath") . "ci/" . $this->repo->full_name . "/" . urlencode($this->project["name"]);
                $imageUrl = Poggit::getSecret("meta.extPath") . "ci.badge/" . $this->repo->full_name . "/" . urlencode($this->project["name"]);
                ?>
                    <img src="<?= $imageUrl ?>"/>
                </span>
            </h1>
            <div id="badgeDialog" title="Status Badge">
                <p><?php EmbedUtils::copyable("Direct URL", $imageUrl) ?></p>
                <p><?php EmbedUtils::copyable("Markdown", "[![Poggit-CI]($imageUrl)]($projectUrl)") ?></p>
                <p><?php EmbedUtils::copyable("BB code", "[URL=\"$projectUrl\"][IMG]{$imageUrl}[/IMG][/URL]") ?></p>
            </div>
            <script>$("#badgeDialog").dialog({autoOpen: false, width: window.innerWidth * 0.8});</script>
            <p>From repo:
                <a href="<?= Poggit::getRootPath() ?>ci/<?= $this->repo->owner->login ?>">
                    <?php EmbedUtils::displayUser($this->repo->owner) ?></a> /
                <a href="<?= Poggit::getRootPath() ?>ci/<?= $this->repo->full_name ?>">
                    <?= $this->repo->name ?></a> <?php EmbedUtils::ghLink($this->repo->html_url) ?>
                <?php if($this->project["path"] !== "") { ?>
                    (Directory <code class="code"><?= htmlspecialchars($this->project["path"]) ?></code>)
                    <?php EmbedUtils::ghLink("https://github.com/{$this->repo->full_name}/tree/{$this->repo->default_branch}/" . $this->project["path"]) ?>
                <?php } ?>
            </p>
            <p>Model: <?= $this->project["framework"] ?></p>
                <h2>Poggit Release <?php EmbedUtils::displayAnchor("releases") ?></h2>
                <?php
                $action = $moduleName = "update";
                if(($this->release === null and $this->preRelease === null) || (($this->release["state"] < PluginRelease::RELEASE_STAGE_RESTRICTED) && !($this->authorized or $this->adminlevel >= Poggit::MODERATOR))) {
                    $action = "release";
                    $moduleName = "submit";
                    ?>
                    <p>This plugin has not been released yet.</p>
                    <?php
                } elseif($this->release === null and $this->preRelease !== null) { // no release, only pre-release
                    ?><div class="latestReleasesPanel"><div class="latestReleaseBox"><?php
                    echo '<h3>Latest pre-release</h3>';
                        if ($this->preRelease["state"] == PluginRelease::RELEASE_STAGE_PENDING)
                            echo '<strong>WARNING: this release is pending reapproval: it may be unsafe</strong>';
                        $this->showRelease($this->preRelease);
                    ?></div></div><?php
                } elseif($this->release !== null) { // if a release exists
                    ?><div class="latestReleasesPanel"><?php
                    if($this->preRelease !== null) { // and if there is a prerelease
                    ?><div class="latestReleaseBox"><?php
                        echo '<h3>Latest pre-release</h3>';
                        if ($this->preRelease["state"] == PluginRelease::RELEASE_STAGE_PENDING)
                            echo '<strong>WARNING: this release is pending reapproval: it may be unsafe</strong>';
                        $this->showRelease($this->preRelease);
                        ?></div><?php
                    }
                    ?><div class="latestReleaseBox"><?php // display release
                    echo '<h3>Latest release</h3>';
                    if ($this->release["state"] == PluginRelease::RELEASE_STAGE_PENDING)
                        echo '<strong>WARNING: this release is pending reapproval: it may be unsafe</strong>';
                    $this->showRelease($this->release); ?></div></div><?php
                }
                ?>
                <?php if((!($this->release === null && $this->preRelease === null)) || $this->authorized) { ?>
                <form id="submitProjectForm" method="post"
                    action="<?= Poggit::getRootPath() ?><?= $moduleName ?>/<?= $this->user ?>/<?= $this->repoName ?>/<?= $this->projectName ?>/<?= $this->latestBuild[1] ?>">
                    <input type="hidden" name="readRules"
                           value="<?= ($this->release === null and $this->preRelease === null) ? "off" : "on" ?>">
                    <p>
                    <select id="submit-chooseBuild" class="inlineselect" onchange="updateSelectedBuild(this)"></select>
                    <span id="view-buttonText" class="action view-buttonText" onclick="window.location = <?= isset($link) ? json_encode($link, JSON_UNESCAPED_SLASHES) : ""?>;">
                    View Release</span>
                    <?php if ($this->authorized) { ?> 
                    <span id="submit-buttonText" class="action" onclick='document.getElementById("submitProjectForm").submit()'>
                    Submit the selected Build for <?= $action ?></span>
                    <?php } ?>
                    </p>
                </form>
                <?php } ?>
            <h2>Build history</h2>
            <p>
                <strong>IMPORTANT! download these builds at your own risk: they may be unsafe</strong><br/>
                <strong>You are strongly advised to use an approved release instead</strong>
            </p>
            <div class="info-table-wrapper">
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
            </div>
            <p><a class="action" onclick="loadMoreHistory(<?= $this->project["projectId"] ?>)">Load more build history</a></p>
            <script>
                loadMoreHistory(<?= $this->project["projectId"] ?>);
            </script>
        </div>
        <?php
    }

    private function showRelease(array $release) {
        ?>
        <p>Name: <img src="<?= $release["icon"] ? $release["icon"]: (Poggit::getRootPath() . "res/defaultPluginIcon") ?>" height="16"/>
            <a href="<?= Poggit::getRootPath() ?>p/<?= htmlspecialchars($release["name"]) ?>/<?= $release["releaseId"] ?>">
                    <?= htmlspecialchars($release["name"]) ?></a>.
            <!-- TODO probably need to support identical names? -->
        </p>
        Version: <?= $release["version"] ?> (<?= $release["releaseCnt"] ?> update<?= $release["releaseCnt"] == 1 ? "" : "s" ?>, <?= $release["dlCount"] ?> download<?= $release["dlCount"] == 1 ? "" : "s" ?>)
        Build: <?= ProjectBuilder::$BUILD_CLASS_HUMAN[$release["class"]] ?>:<?= $release["internal"] ?>
            <?php
    }

    public function og() {
        echo "<meta property='article:author' content='$this->user'/>";
        echo "<meta property='article:section' content='CI'/>";
        return "article";
    }

    public function getMetaDescription(): string {
        return "Builds in $this->projectName in $this->user/$this->repoName by Poggit-CI";
    }
}

// TODO add button for migration of projects from repo to repo
