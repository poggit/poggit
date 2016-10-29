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

use poggit\model\BuildThumbnail;
use poggit\module\VarPage;
use poggit\Poggit;

class RecentBuildPage extends VarPage {
    /** @var string|null */
    private $error = null;

    public function __construct(string $error = "") {
        $this->error = $error;
    }

    public function getTitle() : string {
        return $this->error === "" ? "Recent Builds" : "Builds Not Found";
    }

    public function output() {
        if($this->error !== "") {
            echo "<div id='fallback-error'>$this->error</div><hr>";
        }
        /** @var BuildThumbnail[] $recent */
        $recent = [];
        foreach(Poggit::queryAndFetch("SELECT b.buildId AS bidg, b.internal AS bidi, b.resourceId as brid,
                p.name AS pname, r.owner AS uname, r.name AS rname, unix_timestamp(b.created) AS created
                FROM builds b INNER JOIN projects p ON b.projectId=p.projectId INNER JOIN repos r ON p.repoId=r.repoId
                WHERE class = ? AND private = 0 ORDER BY created DESC LIMIT 20", "i", Poggit::BUILD_CLASS_DEV) as $row) {
            $build = new BuildThumbnail();
            $build->globalId = (int) $row["bidg"];
            $build->internalId = (int) $row["bidi"];
            $build->resourceId = (int) $row["brid"];
            $build->projectName = $row["pname"];
            $build->repoName = $row["rname"];
            $build->repoOwnerName = $row["uname"];
            $build->created = (int) $row["created"];
            $recent[] = $build;
        }
        ?>
        <div id="recentBuilds">
            <?php if($this->error !== "") { ?>
                <p>Here are some recent development builds from other projects:</p>
            <?php } else { ?>
                <h1>Recent builds</h1>
            <?php } ?>
            <!-- TODO add recent build list -->
            <?php foreach($recent as $build) { ?>
                <div class="brief-info">
                    <h2><a style="color: inherit"
                           href="<?= Poggit::getRootPath() ?>ci/<?= $build->repoOwnerName ?>">
                            <?= htmlspecialchars($build->projectName) ?></a>
                    </h2>
                    <p class="remark">Repo:
                        <a href="<?= Poggit::getRootPath() ?>ci/<?= $build->repoOwnerName ?>/<?= $build->repoName ?>/<?= urlencode($build->projectName) ?>">
                            <?= htmlspecialchars($build->repoOwnerName) ?></a>
                        <?php Poggit::ghLink("https://github.com/" . $build->repoOwnerName) ?> /
                        <a href="<?= Poggit::getRootPath() ?>ci/<?= $build->repoOwnerName ?>/<?= $build->repoName ?>">
                            <?= $build->repoName ?></a>
                        <?php Poggit::ghLink("https://github.com/" . urlencode($build->repoOwnerName) . "/" . urlencode($build->repoName)) ?>
                    </p>
                    <p class="remark">
                        Build number:
                        <?php Poggit::showBuildNumbers($build->globalId, $build->internalId, "ci/$build->repoOwnerName/$build->repoName/$build->projectName/$build->internalId") ?>
                    </p>
                    <p class="remark">
                        Created <span class="time-elapse" data-timestamp="<?= $build->created ?>"></span> ago
                    </p>
                </div>
            <?php } ?>
        </div>
        <?php
    }

    public function getError() {
        return $this->error;
    }

    public function setError(string $error) : RecentBuildPage {
        $this->error = $error;
        return $this;
    }

    public function getMetaDescription() : string {
        return "Recent projects around GitHub built by Poggit";
    }
}
