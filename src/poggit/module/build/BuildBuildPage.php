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

use poggit\builder\cause\V2BuildCause;
use poggit\builder\lint\BuildResult;
use poggit\builder\lint\V2BuildStatus;
use poggit\exception\GitHubAPIException;
use poggit\module\VarPage;
use poggit\Poggit;
use poggit\session\SessionUtils;

class BuildBuildPage extends VarPage {
    /** @var string|null */
    public static $projectPath = null;

    /** @var string */
    private $ownerName;
    /** @var string */
    private $repoName;
    /** @var string */
    private $projectName;
    /** @var string */
    private $internalBuildNumber;
    /** @var int */
    private $buildClass;
    /** @var \stdClass */
    private $repo;
    /** @var array */
    private $build;
    /** @var \stdClass[] */
    private $lint;
    /** @var string */
    private $permLink;
    /** @var array|null */
    private $release, $preRelease;

    public function __construct(string $user, string $repo, string $project, string $internalBuildNumber) {
        $this->ownerName = $user;
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
            WHERE r.build = 1 AND r.owner = ? AND r.name = ? AND p.name = ?", "isss", Poggit::BUILD_CLASS_PR, $this->ownerName, $this->repoName, $this->projectName);
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
        
        $class = "dev";
        if(strpos($internalBuildNumber, ":") !== false) {
            list($class, $internalBuildNumber) = explode(":", strtolower($internalBuildNumber), 2);
        }
        switch($class) {
            case "dev":
                $this->buildClass = Poggit::BUILD_CLASS_DEV;
                break;
            case "beta":
                $this->buildClass = Poggit::BUILD_CLASS_BETA;
                break;
            case "rc":
                $this->buildClass = Poggit::BUILD_CLASS_RELEASE;
                break;
            case "pr":
                $this->buildClass = Poggit::BUILD_CLASS_PR;
                break;
        }
        if(!isset($this->buildClass) or !is_numeric($internalBuildNumber)) {
            $rp = json_encode(Poggit::getRootPath(), JSON_UNESCAPED_SLASHES);
            throw new RecentBuildPage(<<<EOD
<p>Invalid request. The #build is not numeric. The correct syntax should be:</p>
<pre><script>document.write(window.location.origin + $rp);</script>ci/$user/$repo/$project/{&lt;buildClass&gt;:}&lt;buildNumber&gt;</pre>
<p>For example:</p>
<pre>
<script>document.write(window.location.origin + $rp);</script>ci/$user/$repo/$project/3
<script>document.write(window.location.origin + $rp);</script>ci/$user/$repo/$project/beta:2
<script>document.write(window.location.origin + $rp);</script>ci/$user/$repo/$project/rc:1
</pre>
EOD
            );
        }
        $this->internalBuildNumber = (int) $internalBuildNumber;

        $session = SessionUtils::getInstance();
        $token = $session->getAccessToken();
        try {
            $this->repo = Poggit::ghApiGet("repos/$this->ownerName/$this->repoName", $token);
        } catch(GitHubAPIException $e) {
            $name = htmlspecialchars($session->getLogin()["name"]);
            $repoNameHtml = htmlspecialchars($user . "/" . $repo);
            throw new RecentBuildPage(<<<EOD
<p>The repo $repoNameHtml does not exist or is not accessible to your GitHub account (<a href="$name"?>@$name</a>).</p>
EOD
            );
        }

        $builds = Poggit::queryAndFetch("SELECT r.owner AS repoOwner, r.name AS repoName, r.private AS isPrivate,
            p.name AS projectName, p.path AS projectPath, p.type AS projectType, p.framework AS projectModel,
            b.buildId AS buildId, b.resourceId AS rsrcId, b.cause AS buildCause,
            b.branch AS buildBranch, b.status AS buildLint, unix_timestamp(b.created) AS buildCreation
            FROM builds b INNER JOIN projects p ON b.projectId = p.projectId 
            INNER JOIN repos r ON p.repoId = r.repoId
            WHERE r.repoId = ? AND r.build = 1 AND p.name = ? AND b.class = ? AND b.internal = ?",
            "isii", $this->repo->id, $this->projectName, $this->buildClass, $this->internalBuildNumber);
        if(count($builds) === 0) {
            $pn = htmlspecialchars($this->projectName);
            throw new RecentBuildPage(<<<EOD
<p>The repo does not have a project called $pn, or the project does not have such a build.</p>
EOD
            );
        }
        $this->build = $builds[0];
        $this->lint = json_decode($this->build["buildLint"]);
        $this->permLink = Poggit::getRootPath() . "babs/" . dechex($this->build["buildId"]);
    }

    public function getTitle(): string {
        return htmlspecialchars("Build #$this->internalBuildNumber | $this->projectName ($this->ownerName/$this->repoName)");
    }

    public function output() {
        $rp = Poggit::getRootPath();
        ?>
        <h1>
            <?= htmlspecialchars($this->projectName) ?>:
            <?= Poggit::$BUILD_CLASS_HUMAN[$this->buildClass] ?> build
            #<?= $this->internalBuildNumber ?>
        </h1>
        <p>
            <a href="<?= $rp ?>build/<?= $this->repo->full_name ?>/<?= urlencode($this->projectName) ?>">
                <?= htmlspecialchars($this->projectName) ?></a> from repo:
            <a href="<?= $rp ?>build/<?= $this->repo->owner->login ?>">
                <?php Poggit::displayUser($this->repo->owner) ?></a>
            / <a href="<?= $rp ?>build/<?= $this->repo->full_name ?>"><?= $this->repo->name ?></a>
            <?php Poggit::ghLink($this->repo->html_url) ?>
            <?php if(trim($this->build["projectPath"], "/") !== "") { ?>
                (In directory <code class="code"><?= htmlspecialchars($this->build["projectPath"]) ?></code>
                <?php Poggit::ghLink($this->repo->html_url . "/tree/" . $this->build["buildBranch"] . "/" .
                    $this->build["projectPath"]) ?>)
            <?php } ?>
        </p>
        <p>Build created: <span class="time" data-timestamp="<?= $this->build["buildCreation"] ?>"></span></p>
        <p>
            Permanent link:
            <a href="<?= $this->permLink ?>">
                <script>document.write(window.location.origin + <?= json_encode($this->permLink) ?>);</script>
            </a>
        </p>
        <h2>This build is triggered by:</h2>
        <?php
        $object = json_decode($this->build["buildCause"]);

        self::$projectPath = $this->build["projectPath"];
        $cause = V2BuildCause::unserialize($object);
        $cause->echoHtml();
        self::$projectPath = null;
        ?>
                        <form id="submitProjectForm" method="post"
                      action="<?= Poggit::getRootPath() ?><?= $moduleName ?>/<?= $this->user ?>/<?= $this->repoName ?>/<?= $this->projectName ?>/<?= implode("/", $this->latestBuild) ?>">
                    <input type="hidden" name="readRules"
                           value="<?= ($this->release === null and $this->preRelease === null) ? "off" : "on" ?>">
                    <p><span class="action" onclick='document.getElementById("submitProjectForm").submit()'>
                    Click this button to submit the latest non-PR build for <?= $action ?>.
                </span></p>
                </form>
        <h2>Lints <?php Poggit::displayAnchor("lints") ?></h2>
        <?php
        foreach($this->lint as $lint) {
            echo '<div class="brief-info">';
            $status = V2BuildStatus::unserialize($lint);
            echo "<p class='remark'>Severity: " . BuildResult::$names[$status->level] . "</p>";
            $status->echoHtml();
            echo '</div>';
        }
    }

    public function og() {
        $c = date(DATE_ISO8601, $this->build["buildCreation"]);
        echo "<meta property='article:published_time' content='$c'/>";
        echo "<meta property='article:author' content='$this->ownerName'/>";
        echo "<meta property='article:section' content='CI'/>";
        return ["article", $this->permLink];
    }

    public function getMetaDescription(): string {
        $perm = dechex($this->build["buildId"]);
        return "Poggit CI Build #$this->internalBuildNumber (&$perm) in $this->projectName in {$this->repo->full_name}";
    }
}
// TODO button to promote build to beta or rc
