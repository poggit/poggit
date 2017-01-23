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
use poggit\embed\ProjectThumbnail;
use poggit\module\VarPage;
use poggit\Poggit;
use poggit\utils\internet\CurlUtils;
use poggit\utils\internet\GitHubAPIException;
use poggit\utils\internet\MysqlUtils;

abstract class RepoListBuildPage extends VarPage {
    /** @var \stdClass[] */
    protected $repos;

    public function __construct() {
        try {
            $repos = $this->getRepos();
        } catch(GitHubAPIException $e) {
            $this->throwNoRepos();
            return;
        }
        $ids = array_map(function ($id) {
            return "p.repoId=$id";
        }, array_keys($repos));
        foreach(MysqlUtils::query("SELECT r.repoId AS rid, p.projectId AS pid, p.name AS pname,
                (SELECT COUNT(*) FROM builds WHERE builds.projectId=p.projectId 
                        AND builds.class IS NOT NULL) AS bcnt,
                IFNULL((SELECT CONCAT_WS(',', buildId, internal) FROM builds WHERE builds.projectId = p.projectId
                        AND builds.class = ? ORDER BY created DESC LIMIT 1), 'null') AS bnum
                FROM projects p INNER JOIN repos r ON p.repoId=r.repoId WHERE r.build=1 AND (" .
            implode(" OR ", $ids) . ") ORDER BY r.name, pname", "i", ProjectBuilder::BUILD_CLASS_DEV) as $projRow) {
            $project = new ProjectThumbnail();
            $project->id = (int) $projRow["pid"];
            $project->name = $projRow["pname"];
            $project->buildCount = (int) $projRow["bcnt"];
            if($projRow["bnum"] === "null") {
                $project->latestBuildGlobalId = null;
                $project->latestBuildInternalId = null;
            } else {
                list($project->latestBuildGlobalId, $project->latestBuildInternalId) = array_map("intval", explode(",", $projRow["bnum"]));
            }
            $repo = $repos[(int) $projRow["rid"]];
            $project->repo = $repo;
            $repo->projects[] = $project;
        }
        $this->repos = $repos;
        if($this instanceof SelfBuildPage) return;
        foreach($this->repos as $repo) {
            if(count($repo->projects) > 0) return;
        }
        $this->throwNoRepos();
    }

    /**
     * @return \stdClass[]
     */
    protected abstract function getRepos(): array;

    /**
     * @param string $url
     * @param string $token
     * @return \stdClass[]
     */
    protected function getReposByGhApi(string $url, string $token): array {
        $repos = [];
        foreach(CurlUtils::ghApiGet($url, $token) as $repo) {
//            if(!$validate($repo)) continue;
            $repo->projects = [];
            $repos[$repo->id] = $repo;
        }
        return $repos;
    }

    protected abstract function throwNoRepos();

    protected abstract function throwNoProjects();

    /**
     * @param \stdClass[] $repos
     */
    protected function displayRepos(array $repos = []) {
        $home = Poggit::getRootPath();
        ?>
        <div class="repolistbuildwrapper">
        <?php
        foreach($repos as $repo) {
            if(count($repo->projects) === 0) continue;
            $opened = "false";
            if(count($repo->projects) === 1) $opened = "true";
            ?>
            <div class="repotoggle" data-name="<?= $repo->full_name ?> (<?= count($repo->projects) ?>)"
                 data-opened="<?= $opened ?>" id="<?= "repo-" . $repo->id ?>">
                <h2>
                    <?php EmbedUtils::displayUser($repo->owner->login, $repo->owner->avatar_url) ?> /
                    <a class="colorless-link" href="<?= $home ?>ci/<?= $repo->full_name ?>"><?= $repo->name ?></a>
                    <?php EmbedUtils::ghLink($repo->html_url) ?>
                </h2>
                <div class="brief-info-wrapper">
                    <?php
                    foreach($repo->projects as $project) {
                        $this->thumbnailProject($project, "brief-info");
                    }
                    ?>
                </div>
            </div>
            <?php
        }
        ?></div><?php
    }

    protected function thumbnailProject(ProjectThumbnail $project, $class = "brief-info") {
        ?>
        <div class="<?= $class ?>" data-project-id="<?= $project->id ?>">
            <h3>
                <a href="<?= Poggit::getRootPath() ?>ci/<?= $project->repo->full_name ?>/<?= urlencode($project->name) ?>">
                    <?= htmlspecialchars($project->name) ?>
                </a>
                <!-- TODO add GitHub link at correct path and ref -->
            </h3>
            <p class="remark">Total: <?= $project->buildCount ?> development
                build<?= $project->buildCount > 1 ? "s" : "" ?></p>
            <p class="remark">
                Last development build:
                <?php
                if($project->latestBuildInternalId !== null or $project->latestBuildGlobalId !== null) {
                    $url = "ci/" . $project->repo->full_name . "/" . urlencode($project->name) . "/" . $project->latestBuildInternalId;
                    EmbedUtils::showBuildNumbers($project->latestBuildGlobalId, $project->latestBuildInternalId, $url);
                } else {
                    echo "No builds yet";
                }
                ?>
            </p>
        </div>
        <?php
    }
}
