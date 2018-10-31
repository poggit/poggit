<?php

/*
 * Poggit
 *
 * Copyright (C) 2016-2018 Poggit
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
use poggit\ci\builder\ProjectBuilder;
use poggit\Config;
use poggit\Mbd;
use poggit\Meta;
use poggit\module\VarPage;
use poggit\release\index\ReleaseListModule;
use poggit\utils\internet\Mysql;
use function htmlspecialchars;
use function http_response_code;
use function strlen;
use function substr;
use function urlencode;

class RecentBuildPage extends VarPage {
    /** @var string|null */
    private $error = null;
    /** @var BuildThumbnail[] */
    private $recent = [];

    public function __construct(string $error, int $responseCode) {
        Session::getInstance();
        $this->error = $error;
        foreach(Mysql::query("SELECT b.buildId, b.internal AS internalId, b.resourceId AS buildRid,
                    p.name AS projectName, r.owner AS uname, r.name AS repoName, unix_timestamp(b.created) AS created
            FROM builds b
            INNER JOIN projects p ON b.projectId = p.projectId
            INNER JOIN repos r ON r.repoId = p.repoId
            WHERE b.buildId IN (SELECT MAX(e.buildId) FROM builds e WHERE e.class = ? GROUP BY e.projectId)
            AND UNIX_TIMESTAMP() - UNIX_TIMESTAMP(created) < ? 
            AND class = ? AND private = 0 AND r.build > 0 ORDER BY created DESC LIMIT 20", "iii",
            ProjectBuilder::BUILD_CLASS_DEV, Config::RECENT_BUILDS_RANGE, ProjectBuilder::BUILD_CLASS_DEV) as $row) {
            $build = new BuildThumbnail();
            $build->globalId = (int) $row["buildId"];
            $build->internalId = (int) $row["internalId"];
            $build->resourceId = (int) $row["buildRid"];
            $build->projectName = $row["projectName"];
            $build->repoName = $row["repoName"];
            $build->repoOwnerName = $row["uname"];
            $build->created = (int) $row["created"];
            $this->recent[] = $build;
        }
        http_response_code($responseCode);
    }

    public function getTitle(): string {
        return $this->error === "" ? "Dev builds today" : "Builds Not Found";
    }

    public function output() {
        if($this->error !== "") {
            echo "<div id='fallback-error'>$this->error</div><hr/>";
        }
        ?>
      <div class="guest-ci-wrapper">
        <div class="recent-builds-header">
            <?php if($this->error !== "") { ?>
              <p>Here are some recent development builds from other projects:</p>
            <?php } else { ?>
              <h4>Recent builds</h4>
              <p>These are <em>development</em> builds. They have not been reviewed, and may contain dangerous code
                <em>including viruses</em>. Think twice before using these builds.<br/>
                For <em>stable</em> and <em>safe</em> releases, please visit <a href="<?= Meta::root() ?>plugins">
                      <?= ReleaseListModule::DISPLAY_NAME ?></a>.</p>
            <?php } ?>
        </div>
        <div id="recentBuilds" class="recent-builds">
            <?php foreach($this->recent as $build) {
                $truncatedName = htmlspecialchars(substr($build->projectName, 0, 14) . (strlen($build->projectName) > 14 ? "..." : ""));
                ?>
              <div class="brief-info">
                <h5><a style="color: inherit;"
                       href="<?= Meta::root() . "ci/$build->repoOwnerName/$build->repoName/" . urlencode($build->projectName) ?>">
                        <?= htmlspecialchars($truncatedName) ?></a>
                </h5>
                <p class="remark">
                  <a href="<?= Meta::root() ?>ci/<?= $build->repoOwnerName ?>/">
                      <?= htmlspecialchars($build->repoOwnerName) ?></a>
                    <?php Mbd::ghLink("https://github.com/" . $build->repoOwnerName) ?> /
                  <a href="<?= Meta::root() ?>ci/<?= $build->repoOwnerName ?>/<?= $build->repoName ?>">
                      <?= $build->repoName ?></a>
                    <?php Mbd::ghLink("https://github.com/" . urlencode($build->repoOwnerName) . "/" . urlencode($build->repoName)) ?>
                </p>
                <p class="remark">
                  Build:
                    <?php Mbd::showBuildNumbers($build->globalId, $build->internalId, "ci/$build->repoOwnerName/$build->repoName/$build->projectName/$build->internalId") ?>
                </p>
                <p class="remark">
                  <span class="time-elapse" data-timestamp="<?= $build->created ?>"></span> ago
                </p>
              </div>
            <?php } ?>
        </div>
      </div>
        <?php
    }

    public function getError() {
        return $this->error;
    }

    public function setError(string $error): RecentBuildPage {
        $this->error = $error;
        return $this;
    }

    public function getMetaDescription(): string {
        return "Recent projects around GitHub built by Poggit";
    }

}
