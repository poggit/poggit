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

namespace poggit\ci\ui;

use poggit\account\Session;
use poggit\ci\api\ProjectSubToggleAjax;
use poggit\ci\builder\ProjectBuilder;
use poggit\Mbd;
use poggit\Meta;
use poggit\module\VarPage;
use poggit\release\Release;
use poggit\utils\internet\Curl;
use poggit\utils\internet\GitHubAPIException;
use poggit\utils\internet\Mysql;

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
    /** @var bool */
    private $authorized;
    /** @var int */
    private $adminLevel = 0;
    /** @var int */
    private $repoId;
    /** @var array|null */
    private $release, $preRelease;
    /** @var int[] */
    private $subs = [];

    public function __construct(string $user, string $repo, string $projectName) {
        $this->user = $user;
        $this->repoName = $repo;
        $this->projectName = $projectName === "~" ? $repo : $projectName;
        $this->authorized = false;
        $session = Session::getInstance();
        $this->adminLevel = Meta::getAdmlv($session->getName()) ?? 0;
        $token = $session->getAccessToken();
        try {
            $this->repo = Curl::ghApiGet("repos/$user/$repo", $token ?: Meta::getDefaultToken());
            $this->authorized = $session->isLoggedIn() && isset($this->repo->permissions) && $this->repo->permissions->admin;
        } catch(GitHubAPIException $e) {
            $name = htmlspecialchars($session->getName());
            $repoNameHtml = htmlspecialchars($user . "/" . $repo);
            throw new RecentBuildPage(<<<EOD
<p>The repo $repoNameHtml does not exist or is not accessible to your GitHub account (<a href="$name">@$name</a>).</p>
EOD
            );
        }
        $this->repoId = $this->repo->id;
        $project = Mysql::query("SELECT t.*, builds.main, builds.buildId, builds.internal FROM
        (SELECT repos.repoId, owner rowner, repos.name rname, private > 0 private,
            projects.name, type, framework, projects.projectId, path,
            (SELECT MAX(buildId) FROM builds WHERE projects.projectId = builds.projectId AND builds.class = ?) AS latestBuild
            FROM projects INNER JOIN repos ON projects.repoId = repos.repoId
            WHERE repos.build = 1 AND repos.repoId = ? AND projects.name = ?) t
        LEFT JOIN builds ON builds.buildId = t.latestBuild", "iis", ProjectBuilder::BUILD_CLASS_DEV, $this->repoId, $this->projectName);
        if(count($project) === 0) {
            throw new RecentBuildPage(<<<EOD
<p>Such project does not exist, or the repo does not have Poggit CI enabled.</p>
EOD
            );
        }
        $this->project = $project[0];
        $this->project["private"] = (bool) (int) $this->project["private"];
        $this->project["type"] = (int) $this->project["type"];
        $this->project["buildId"] = (int) $this->project["buildId"];
        $this->project["internal"] = (int) $this->project["internal"];
        $projectId = $this->project["projectId"] = (int) $this->project["projectId"];

        $allReleases = Mysql::query("SELECT name, releaseId, releases.buildId, b.internal, b.class, state, version, releases.flags, icon, art.dlCount,
            (SELECT COUNT(*) FROM releases ra WHERE ra.projectId = releases.projectId) AS releaseCnt
             FROM releases
             INNER JOIN resources art ON releases.artifact = art.resourceId
             INNER JOIN builds b ON b.buildId = releases.buildId
             WHERE releases.projectId = ? ORDER BY releases.creation DESC", "i", $projectId);
        if(count($allReleases) !== 0) {
            $latestRelease = $allReleases[0];
            $latestRelease["releaseId"] = (int) $latestRelease["releaseId"];
            $flags = $latestRelease["flags"] = (int) $latestRelease["flags"];
            $latestRelease["releaseCnt"] = (int) $latestRelease["releaseCnt"];
            $latestRelease["dlCount"] = (int) $latestRelease["dlCount"];
            $latestRelease["buildId"] = (int) $latestRelease["buildId"];
            $latestRelease["internal"] = (int) $latestRelease["internal"];
            $latestRelease["state"] = (int) $latestRelease["state"];

            if($flags & Release::FLAG_PRE_RELEASE) {
                $this->preRelease = $latestRelease;
                $latestRelease = Mysql::query("SELECT name, releaseId, version, icon, art.dlCount, b.internal, b.class, state,
                    (SELECT COUNT(*) FROM releases ra WHERE ra.projectId = releases.projectId AND ra.creation <= releases.creation) AS releaseCnt
                    FROM releases
                    INNER JOIN builds b ON b.buildId = releases.buildId
                    INNER JOIN resources art ON releases.artifact = art.resourceId
                    WHERE releases.projectId = ? AND (flags & ?) = 0 ORDER BY releases.creation DESC LIMIT 1", "ii", $projectId, Release::FLAG_PRE_RELEASE);
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

        foreach(Mysql::query("SELECT userId, level FROM project_subs WHERE projectId = ? AND level > ?", "ii", $this->project["projectId"], ProjectSubToggleAjax::LEVEL_NONE) as $row) {
            $this->subs[(int) $row["userId"]] = (int) $row["level"];
        }
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
            <h2>
                <?php if($this->project["type"] !== ProjectBuilder::PROJECT_TYPE_SPOON) { ?>
                    <?= ProjectBuilder::$PROJECT_TYPE_HUMAN[$this->project["type"]] ?> project:
                <?php } ?>
                <a href="<?= Meta::root() ?>ci/<?= $this->repo->full_name ?>/<?= urlencode(
                    $this->project["name"] === $this->repo->name ? "~" : $this->project["name"]) ?>">
                    <?= htmlspecialchars($this->project["name"]) ?>
                </a>
                <?php if($this->repo->private) { ?>
                    <img title="This is a private repo" width="16"
                         src="https://maxcdn.icons8.com/Android_L/PNG/24/Very_Basic/lock-24.png"/>
                <?php } ?>
                <?php Mbd::ghLink($this->repo->html_url . "/tree/" . $this->repo->default_branch . "/" . $this->project["path"]) ?>
                <span style="cursor: pointer;" onclick="$('#badgeDialog').dialog('open')">
                <?php
                $projectUrl = Meta::getSecret("meta.extPath") . "ci/" . $this->repo->full_name . "/" . urlencode($this->project["name"]);
                $imageUrl = Meta::getSecret("meta.extPath") . "ci.badge/" . $this->repo->full_name . "/" . urlencode($this->project["name"]);
                ?>
                    <img src="<?= $imageUrl ?>"/>
                </span>
            </h2>
            <div id="badgeDialog" title="Status Badge">
                <p><?php Mbd::copyable("Direct URL", $imageUrl) ?></p>
                <p><?php Mbd::copyable("Markdown", "[![Poggit-CI]($imageUrl)]($projectUrl)") ?></p>
                <p><?php Mbd::copyable("BB code", "[URL=\"$projectUrl\"][IMG]{$imageUrl}[/IMG][/URL]") ?></p>
            </div>
            <script>$("#badgeDialog").dialog({autoOpen: false, width: window.innerWidth * 0.8});</script>
            <p>From repo:
                <a href="<?= Meta::root() ?>ci/<?= $this->repo->owner->login ?>">
                    <?php Mbd::displayUser($this->repo->owner) ?></a> /
                <a href="<?= Meta::root() ?>ci/<?= $this->repo->full_name ?>">
                    <?= $this->repo->name ?></a> <?php Mbd::ghLink($this->repo->html_url) ?>
                <?php if($this->project["path"] !== "") { ?>
                    (Directory <code class="code"><?= htmlspecialchars($this->project["path"]) ?></code>)
                    <?php Mbd::ghLink("https://github.com/{$this->repo->full_name}/tree/{$this->repo->default_branch}/" . $this->project["path"]) ?>
                <?php } ?>
            </p>
            <p>
                <?php if($this->project["type"] !== ProjectBuilder::PROJECT_TYPE_SPOON) { ?>
                Model: <?= htmlspecialchars($this->project["framework"]) ?><br/>
                <?php } ?>
                <?php if($this->project["type"] === ProjectBuilder::PROJECT_TYPE_PLUGIN) { ?>
                    Main class: <?= htmlspecialchars($this->project["main"] ?? "N/A") ?><br/>
                <?php } elseif($this->project["type"] === ProjectBuilder::PROJECT_TYPE_LIBRARY) { ?>
                    Antigen: <?= htmlspecialchars($this->project["main"] ?? "N/A") ?><br/>
                <?php } ?>
                Project ID: <?= $this->project["projectId"] ?><br/>
                Subscribers: <?= count($this->subs) ?>
                <?php if(Session::getInstance()->isLoggedIn()) { ?>
                    <span onclick='toggleProjectSub(<?= $this->project["projectId"] ?>,
                            document.getElementById("select-project-sub").value)'
                          class="action" id="project-subscribe">
                    Change subscription</span> to
                    <select id="select-project-sub">
                        <?php foreach(ProjectSubToggleAjax::$LEVELS_TO_HUMAN as $level => $human) { ?>
                            <option value="<?= $level ?>"
                                <?= ($this->subs[Session::getInstance()->getUid()] ??
                                    ProjectSubToggleAjax::LEVEL_NONE) === $level ? "selected" : "" ?>>
                                <?= htmlspecialchars($human) ?></option>
                        <?php } ?>
                    </select>
                <?php } ?>
            </p>
            <h5>Poggit Release <?php Mbd::displayAnchor("releases") ?></h5>
            <?php
            $action = $moduleName = "update";
            if(($this->release === null and $this->preRelease === null) || (($this->release["state"] < Release::STATE_CHECKED) && !($this->authorized or $this->adminLevel >= Meta::ADMLV_MODERATOR))) {
                $action = "release";
                $moduleName = "submit";
                ?>
                <p>This plugin has not been released yet.</p>
                <?php
            } elseif($this->release === null and $this->preRelease !== null) { // no release, only pre-release
                ?>
                <div class="latestReleasesPanel">
                <div class="latestReleaseBox"><?php
                    echo '<h5>Latest pre-release</h5>';
                    $this->showRelease($this->preRelease);
                    ?></div></div><?php
            } elseif($this->release !== null) { // if a release exists
                ?>
                <div class="latestReleasesPanel">
                    <?php if($this->preRelease !== null) { // and if there is a prerelease ?>
                        <div class="latestReleaseBox">
                            <h5>Latest pre-release</h5>
                            <?php $this->showRelease($this->preRelease); ?>
                        </div>
                    <?php } ?>
                    <div class="latestReleaseBox">
                        <h5>Latest release</h5>
                        <?php $this->showRelease($this->release); // display release ?>
                    </div>
                </div>
            <?php } ?>
            <?php if((!($this->release === null && $this->preRelease === null)) || $this->authorized) { ?>
                <form id="submitProjectForm" method="post"
                      action="<?= Meta::root() . $moduleName . "/" . $this->user . "/" . $this->repoName . "/" .
                      $this->projectName . "/" . $this->project["internal"] ?>">
                    <input type="hidden" name="readRules"
                           value="<?= ($this->release === null and $this->preRelease === null) ? "off" : "on" ?>">
                    <p>
                        <select id="submit-chooseBuild" class="inlineselect"
                                onchange="updateSelectedBuild(this)"></select>
                        <span id="view-buttonText" class="action view-buttonText">View Release</span>
                        <?php if($this->authorized) { ?>
                            <span id="submit-buttonText" class="action"
                                  onclick='document.getElementById("submitProjectForm").submit()'>
                    Submit the selected Build for <?= $action ?></span>
                        <?php } ?>
                    </p>
                </form>
            <?php } ?>
            <?php if($this->project["type"] === ProjectBuilder::PROJECT_TYPE_LIBRARY and $this->project["framework"] === "virion") { ?>
                <h5>Virion Usage</h5>
                <div class="info-virion-usage">
                    <?php
                    $rows = Mysql::query("SELECT up.name project, COUNT(ub.buildId) builds
                        FROM virion_usages vu
                        INNER JOIN builds vb ON vu.virionBuild = vb.buildId
                            INNER JOIN projects vp ON vb.projectId = vp.projectId
                        INNER JOIN builds ub ON vu.userBuild = ub.buildId
                            INNER JOIN projects up ON ub.projectId = up.projectId
                        WHERE vp.projectId = ?
                        GROUP BY up.projectId", "i", $this->project["projectId"]);
                    foreach($rows as $row) {
                        echo "<p>Used in " . $row["builds"] . " builds of " . $row["project"] . "</p>";
                    }
                    ?>
                </div>
            <?php } ?>
            <h5>Build history</h5>
            <?php if($this->repoId !== 69691727) { ?>
                <p>
                    <strong>IMPORTANT! download these builds at your own risk: they may be unsafe</strong><br/>
                    <strong>You are strongly advised to use an approved release instead</strong>
                </p>
            <?php } ?>
            Show branch:
            <?php $branch = $_REQUEST["branch"] ?? "all"; ?>
            <select id="buildHistoryBranchFilter" class="inlineselect" onchange="doBuildHistoryFilter()">
                <option value="all" <?= $branch === "all" ? "selected" : "" ?>>(All Branches)</option>
                <?php foreach(Mysql::query("SELECT branch, MAX(created) AS late, COUNT(*) AS cnt FROM builds WHERE projectId = ? GROUP BY branch ORDER BY late DESC", "i", $this->project["projectId"]) as $row) { ?>
                    <option value="<?= htmlspecialchars($row["branch"]) ?>" <?= $branch === $row["branch"] ? "selected" : "" ?>>
                        <?= htmlspecialchars($row["branch"]) ?> (<?= (int) $row["cnt"] ?>)
                    </option>
                <?php } ?>
            </select>
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
            <a class="action" onclick="loadMoreHistory(<?= json_encode($this->project["projectId"]) ?>)">Load more build
                history</a>
            <script>
                loadMoreHistory(<?= json_encode($this->project["projectId"]) ?>);
            </script>
        </div>
        <?php
    }

    private function showRelease(array $release) {
        ?>
        <p>Name:
            <img height="16"
                 src="<?= Mbd::esq($release["icon"] ?: (Meta::root() . "res/defaultPluginIcon2.png")) ?>"/>
            <a href="<?= Meta::root() ?>p/<?= urlencode($release["name"]) ?>/<?= $release["version"] ?>">
                <?= htmlspecialchars($release["name"]) ?></a>.
            <!-- TODO probably need to support identical names? -->
        </p>
        Version: <?= htmlspecialchars($release["version"]) ?>
        (<?= Mbd::quantitize($release["releaseCnt"], "update") ?>, <?= Mbd::quantitize($release["dlCount"], "download") ?>)
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
