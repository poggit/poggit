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
use poggit\builder\lint\V2BuildStatus;
use poggit\exception\GitHubAPIException;
use poggit\Poggit;
use poggit\session\SessionUtils;

class BuildBuildPage extends BuildPage {
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
    private $buildClass;
    /** @var \stdClass */
    private $repo;
    /** @var array */
    private $build;
    /** @var \stdClass[] */
    private $lint;

    public function __construct(string $user, string $repo, string $project, string $internalBuildNumber) {
        $this->ownerName = $user;
        $this->repoName = $repo;
        $this->projectName = $project;
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
            throw new AltBuildPageException(new RecentBuildPage(<<<EOD
<p>Invalid request. The #build is not numeric. The correct syntax should be:</p>
<pre><script>document.write(window.location.origin + $rp);</script>build/$user/$repo/$project/{&lt;buildClass&gt;:}&lt;buildNumber&gt;</pre>
<p>For example:</p>
<pre>
<script>document.write(window.location.origin + $rp);</script>build/$user/$repo/$project/3
<script>document.write(window.location.origin + $rp);</script>build/$user/$repo/$project/beta:2
<script>document.write(window.location.origin + $rp);</script>build/$user/$repo/$project/rc:1
</pre>
EOD
            ));
        }
        $this->internalBuildNumber = (int) $internalBuildNumber;

        $session = SessionUtils::getInstance();
        $token = $session->getAccessToken();
        try {
            $this->repo = Poggit::ghApiGet("repos/$this->ownerName/$this->repoName", $token);
        } catch(GitHubAPIException $e) {
            $name = htmlspecialchars($session->getLogin()["name"]);
            $repoNameHtml = htmlspecialchars($user . "/" . $repo);
            throw new AltBuildPageException(new RecentBuildPage(<<<EOD
<p>The repo $repoNameHtml does not exist or is not accessible to your GitHub account (<a href="$name"?>@$name</a>).</p>
EOD
            ));
        }

        $builds = Poggit::queryAndFetch("SELECT r.owner AS repoOwner, r.name AS repoName, r.private AS isPrivate,
            p.name AS projectName, p.path AS projectPath, p.type AS projectType, p.framework AS projectModel,
            b.buildId AS buildId, b.resourceId AS rsrcId, b.cause AS buildCause,
            b.branch AS buildBranch, b.status AS buildLint, unix_timestamp(b.created) AS buildCreation
            FROM builds b INNER JOIN projects p ON b.projectId = p.projectId INNER JOIN repos r ON p.repoId = r.repoId
            WHERE r.repoId = ? AND r.build = 1 AND p.name = ? AND b.class = ? AND b.internal = ?", "isii",
            $this->repo->id, $this->projectName, $this->buildClass, $this->internalBuildNumber);
        if(count($builds) === 0) {
            $pn = htmlspecialchars($this->projectName);
            throw new AltBuildPageException(new RecentBuildPage(<<<EOD
<p>The repo does not have a project called $pn, or the project does not have such a build.</p>
EOD
            ));
        }
        $this->build = $builds[0];
        $this->lint = json_decode($this->build["buildLint"]);
    }

    public function getTitle() : string {
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
            <a href="<?= $rp ?>build/<?= $this->repo->owner->login ?>"><?= $this->repo->owner->login ?></a>
            <?php Poggit::ghLink($this->repo->owner->html_url) ?>
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
            <a href="<?= $link = Poggit::getRootPath() . "babs/" . dechex($this->build["buildId"]) ?>">
                <script>document.write(window.location.origin + <?= json_encode($link) ?>);</script>
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
        <h2>Lint</h2>
        <?php
        foreach($this->lint as $lint) {
            echo '<div class="lint-section">';
            // TODO format
            $status = V2BuildStatus::unserialize($lint);
            $status->echoHtml();
            echo '</div>';
        }
    }
}
