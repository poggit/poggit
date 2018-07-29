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

use poggit\ci\builder\ProjectBuilder;
use poggit\Mbd;
use poggit\Meta;
use poggit\module\VarPage;
use poggit\utils\internet\GitHub;
use poggit\utils\internet\GitHubAPIException;
use poggit\utils\internet\Mysql;
use stdClass;
use function array_keys;
use function array_map;
use function count;
use function explode;
use function htmlspecialchars;
use function max;
use function str_repeat;
use function substr;
use function urlencode;
use function usort;

abstract class RepoListBuildPage extends VarPage {
    /** @var stdClass[] */
    protected $repos;

    public function __construct() {
        try {
            $repos = $this->getRepos();
        } catch(GitHubAPIException $e) {
            $this->throwNoRepos();
            return;
        }
        $ids = array_keys($repos);
        $idsImploded = substr(str_repeat(",?", count($ids)), 1);
        foreach(count($ids) === 0 ? [] : Mysql::query("SELECT r.repoId AS rid, p.projectId AS pid, p.name AS projectName, p.path,
                (SELECT UNIX_TIMESTAMP(created) FROM builds WHERE builds.projectId=p.projectId AND builds.class IS NOT NULL
                    ORDER BY created DESC LIMIT 1) AS buildDate,
                (SELECT COUNT(*) FROM builds WHERE builds.projectId=p.projectId AND builds.class IS NOT NULL) AS buildCount,
                IFNULL((SELECT CONCAT_WS(',', buildId, internal) FROM builds
                    WHERE builds.projectId = p.projectId AND builds.class = ? AND p.repoId IN ($idsImploded)
                    ORDER BY created DESC LIMIT 1), 'null') AS buildNumbers
                FROM projects p INNER JOIN repos r ON p.repoId=r.repoId WHERE r.build=1 ORDER BY r.name, projectName", "i" . str_repeat("i", count($ids)), ProjectBuilder::BUILD_CLASS_DEV, ...$ids) as $projRow) {
            $repo = $repos[(int) $projRow["rid"]] ?? null;
            if(!isset($repo)) {
//                Meta::getLog()->jwtf($repos); // FixMe This gets called occasionally!
//                Meta::getLog()->jwtf($projRow["rid"]);
                continue;
            }
            $project = new ProjectThumbnail();
            $project->id = (int) $projRow["pid"];
            $project->name = $projRow["projectName"];
            $project->path = $projRow["path"];
            $project->buildCount = (int) $projRow["buildCount"];
            $project->buildDate = $projRow["buildDate"];

            if($projRow["buildNumbers"] === "null") {
                $project->latestBuildGlobalId = null;
                $project->latestBuildInternalId = null;
            } else {
                list($project->latestBuildGlobalId, $project->latestBuildInternalId) = array_map("intval", explode(",", $projRow["buildNumbers"]));
            }
            $project->repo = $repo;
            $repo->projects[] = $project;
        }
        $this->repos = $repos;
        usort($this->repos, function($a, $b) {
            if(count($a->projects) === 0) return -1;
            if(count($b->projects) === 0) return 1;
            $maxBuildMapper = function(ProjectThumbnail $project) {
                return $project->buildDate;
            };
            return -(max(array_map($maxBuildMapper, $a->projects)) <=> max(array_map($maxBuildMapper, $b->projects)));
        });
        if($this instanceof SelfBuildPage) return;
        foreach($this->repos as $repo) {
            if(count($repo->projects) > 0) return;
        }
        $this->throwNoRepos();
    }

    /**
     * @return stdClass[]
     */
    protected abstract function getRepos(): array;

    /**
     * @param string $url
     * @param string $token
     * @return stdClass[]
     */
    protected function getReposByGhApi(string $url, string $token): array {
        $repos = [];
        foreach(GitHub::ghApiGet($url, $token ?: Meta::getDefaultToken()) as $repo) {
//            if(!$validate($repo)) continue;
            $repo->projects = [];
            $repos[$repo->id] = $repo;
        }
        return $repos;
    }

    protected abstract function throwNoRepos();

    protected abstract function throwNoProjects();

    /**
     * @param stdClass[] $repos
     */
    protected function displayRepos(array $repos = []) {
        $home = Meta::root();
        ?>
      <div id="repo-list-build-wrapper">
          <?php
          foreach($repos as $repo) {
              if(count($repo->projects) === 0) continue;
              $opened = "false";
              if(count($repo->projects) === 1) $opened = "true";
              ?>
              <?php foreach($repo->projects as $project) { ?>
              <div class="repo-toggle" data-name="<?= $repo->full_name ?> (<?= count($repo->projects) ?>)"
                   data-opened="<?= $opened ?>" id="<?= "repo-" . $repo->id ?>">
                <h5>
                    <?php Mbd::displayUser($repo->owner->login, "https://github.com/{$repo->owner->login}.png") ?>
                  <br>
                </h5>
                <nobr><a class="colorless-link"
                         href="<?= $home ?>ci/<?= $repo->full_name ?>"><?= $repo->name ?></a>
                    <?php Mbd::ghLink("https://github.com/{$repo->full_name}") ?></nobr>
                <div class="brief-info-wrapper">
                    <?php $this->thumbnailProject($project, "brief-info") ?>
                </div>
              </div>
              <?php } ?>
              <?php
          }
          ?>
      </div>
        <?php
    }

    protected function thumbnailProject(ProjectThumbnail $project, $class) {
        ?>
      <div class="<?= $class ?>" data-project-id="<?= $project->id ?>">
        <h5>
          <a href="<?= Meta::root() ?>ci/<?= $project->repo->full_name ?>/<?= $project->name === $project->repo->name ?
              "~" : urlencode($project->name) ?>">
              <?= htmlspecialchars($project->name) ?>
          </a>
            <?php Mbd::ghLink("https://github.com/{$project->repo->full_name}/tree/{$project->repo->default_branch->name}/{$project->path}") ?>
        </h5>
        <p class="remark">Total: <?= $project->buildCount ?> development
          build<?= $project->buildCount > 1 ? "s" : "" ?></p>
        <p class="remark">Latest: <span class="time-elapse" data-timestamp="<?= $project->buildDate ?>"></span></p>
          <?php
          if($project->latestBuildInternalId !== null or $project->latestBuildGlobalId !== null) {
              $url = "ci/" . $project->repo->full_name . "/" . urlencode($project->name) . "/" . $project->latestBuildInternalId;
              Mbd::showBuildNumbers($project->latestBuildGlobalId, $project->latestBuildInternalId, $url);
          } else {
              echo "No builds yet";
          }
          ?>
      </div>
        <?php
    }
}
