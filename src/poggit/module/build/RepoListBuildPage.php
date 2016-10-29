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
use poggit\model\ProjectThumbnail;
use poggit\module\VarPage;
use poggit\Poggit;

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
        if(count($repos) === 0) $this->throwNoRepos();
        $ids = array_map(function ($id) {
            return "p.repoId=$id";
        }, array_keys($repos));
        foreach(Poggit::queryAndFetch("SELECT r.repoId AS rid, p.projectId AS pid, p.name AS pname,
                (SELECT COUNT(*) FROM builds WHERE builds.projectId=p.projectId 
                        AND builds.class IS NOT NULL) AS bcnt,
                IFNULL((SELECT CONCAT_WS(',', buildId, internal) FROM builds WHERE builds.projectId = p.projectId
                        AND builds.class = ? ORDER BY created DESC LIMIT 1), 'null') AS bnum
                FROM projects p INNER JOIN repos r ON p.repoId=r.repoId WHERE r.build=1 AND (" .
            implode(" OR ", $ids) . ") ORDER BY r.name, pname", "i", Poggit::BUILD_CLASS_DEV) as $projRow) {
            $project = new ProjectThumbnail();
            $project->id = (int) $projRow["pid"];
            $project->name = $projRow["pname"];
            $project->buildCount = (int) $projRow["bcnt"];
            if($projRow["bnum"] === "null") {
                $project->latestBuildGlobalId = null;
                $project->latestBuildInternalId = null;
            } else list($project->latestBuildGlobalId, $project->latestBuildInternalId) = array_map("intval", explode(",", $projRow["bnum"]));
            $repo = $repos[(int) $projRow["rid"]];
            $project->repo = $repo;
            $repo->projects[] = $project;
        }
        foreach($repos as $id => $repo) {
            if(count($repo->projects) === 0) unset($repos[$id]);
        }
        if(count($repos) === 0) $this->throwNoProjects();
        $this->repos = $repos;
    }

    /**
     * @return \stdClass[]
     */
    protected abstract function getRepos() : array;

    /**
     * @param string $url
     * @param string $token
     * @return \stdClass[]
     */
    protected function getReposByGhApi(string $url, string $token) : array {
        $repos = [];
        foreach(Poggit::ghApiGet($url, $token) as $repo) {
//            if(!$validate($repo)) continue;
            $repo->projects = [];
            $repos[$repo->id] = $repo;
        }
        return $repos;
    }

    protected abstract function throwNoRepos();

    protected abstract function throwNoProjects();

    public function output() {
        $this->displayRepos($this->repos);
    }

    /**
     * @param \stdClass[] $repos
     */
    protected function displayRepos(array $repos) {
        $home = Poggit::getRootPath();
        foreach($repos as $repo) {
            if(count($repo->projects) === 0) continue;
            $opened = "false";
            if(count($repo->projects) === 1) $opened = "true";
            ?>
            <div class="toggle" data-name="<?= $repo->full_name ?> (<?= count($repo->projects) ?>)"
                 data-opened="<?= $opened ?>">
                <h2><a href="<?= $home ?>ci/<?= $repo->full_name ?>">Projects</a> in
                    <?php Poggit::displayRepo($repo->owner->login, $repo->name, $repo->owner->avatar_url) ?>
                </h2>
                <?php
                foreach($repo->projects as $project) {
                    $this->thumbnailProject($project);
                }
                ?>
            </div>
            <?php
        }
    }

    protected function thumbnailProject(ProjectThumbnail $project) {
        ?>
        <div class="brief-info" data-project-id="<?= $project->id ?>">
            <h3>
                <a href="<?= Poggit::getRootPath() ?>ci/<?= $project->repo->full_name ?>/<?= urlencode($project->name) ?>">
                    <?= htmlspecialchars($project->name) ?>
                </a>
                <!-- TODO add GitHub link at correct path and ref -->
            </h3>
            <p class="remark">Totally <?= $project->buildCount ?> development
                build<?= $project->buildCount > 1 ? "s" : "" ?></p>
            <p class="remark">
                Last development build:
                <?php
                if($project->latestBuildInternalId !== null or $project->latestBuildGlobalId !== null) {
                    $url = "ci/" . $project->repo->full_name . "/" . urlencode($project->name) . "/" . $project->latestBuildInternalId;
                    Poggit::showBuildNumbers($project->latestBuildGlobalId, $project->latestBuildInternalId, $url);
                } else {
                    echo "No builds yet";
                }
                ?>
            </p>
        </div>
        <?php
    }
}
