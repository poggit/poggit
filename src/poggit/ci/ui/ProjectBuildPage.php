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
use poggit\ci\api\ProjectSubToggleAjax;
use poggit\ci\builder\ProjectBuilder;
use poggit\Mbd;
use poggit\Meta;
use poggit\module\Module;
use poggit\module\VarPage;
use poggit\release\Release;
use poggit\utils\internet\GitHub;
use poggit\utils\internet\Mysql;
use stdClass;
use function array_slice;
use function count;
use function date;
use function dechex;
use function end;
use function explode;
use function htmlspecialchars;
use function implode;
use function json_encode;
use function urlencode;
use const JSON_UNESCAPED_SLASHES;

class ProjectBuildPage extends VarPage {
    /** @var BuildModule */
    private $module;

    /** @var string */
    private $user;
    /** @var string */
    private $repoName;
    /** @var string */
    private $projectName;

    /** @var array */
    private $project;
    /** @var stdClass|null */
    private $release = null, $preRelease = null;
    /** @var int[] */
    private $subs = [];
    private $readPerm, $writePerm;

    public function __construct(BuildModule $module, string $user, string $repo, string $projectName) {
        $this->module = $module;
        $this->user = $user;
        $this->repoName = $repo;
        $this->projectName = $projectName === "~" ? $repo : $projectName;
        $session = Session::getInstance();
        $this->readPerm = $readPerm = GitHub::testPermission("$user/$repo", $session->getAccessToken(true), $session->getName(), "pull");
        $this->writePerm = $writePerm = GitHub::testPermission("$user/$repo", $session->getAccessToken(true), $session->getName(), "push");
        if(!$readPerm) {
            $name = htmlspecialchars($session->getName());
            $repoNameHtml = htmlspecialchars($user . "/" . $repo);
            throw new RecentBuildPage(<<<EOD
<p>The repo $repoNameHtml does not exist or is not accessible to your GitHub account (<a href="$name">@$name</a>).</p>
EOD
                , 404);
        }
        $projects = Mysql::query("SELECT
                repoId, repoOwner, repoName, private, projectName, projectType, projectModel, t.projectId, projectPath,
                lastBuild.main, lastBuild.buildId, lastBuild.internal
            FROM (SELECT repos.repoId, owner repoOwner, repos.name repoName, private > 0 private,
                projects.name projectName, type projectType, framework projectModel, projects.projectId, path projectPath,
                (SELECT MAX(buildId) FROM builds WHERE projects.projectId = builds.projectId AND builds.class = ?) maxBuild
                FROM projects INNER JOIN repos ON projects.repoId = repos.repoId
                WHERE repos.build = 1 AND repos.owner = ? AND repos.name = ? AND projects.name = ?) t
        LEFT JOIN builds lastBuild ON lastBuild.buildId = t.maxBuild", "isss", ProjectBuilder::BUILD_CLASS_DEV, $this->user, $this->repoName, $this->projectName);
        if(count($projects) === 0) {
            throw new RecentBuildPage(<<<EOD
<p>Such project does not exist, or the repo does not have Poggit CI enabled.</p>
EOD
                , 404);
        }
        $this->project = (object) $projects[0];
        $this->project->private = (bool) (int) $this->project->private;
        $this->project->projectType = (int) $this->project->projectType;
        $this->project->buildId = (int) $this->project->buildId;
        $this->project->internal = (int) $this->project->internal;
        $this->project->projectId = (int) $this->project->projectId;

        $lastReleases = Mysql::query("SELECT IF(pre > 0, 1, 0) isPreRelease, releaseId, name, version, UNIX_TIMESTAMP(creation) creation, state, releases.buildId
            FROM (SELECT (flags & ?) pre, MAX(releaseId) maxReleaseId FROM releases WHERE projectId = ? AND state >= ? GROUP BY pre) t
            INNER JOIN releases ON t.maxReleaseId = releases.releaseId", "iii",
            Release::FLAG_PRE_RELEASE, $this->project->projectId, $writePerm ? Release::STATE_SUBMITTED : Release::STATE_CHECKED);
        foreach($lastReleases as $row) {
            $release = (object) $row;
            $release->releaseId = (int) $release->releaseId;
            $release->creation = (int) $release->creation;
            $release->state = (int) $release->state;
            if((int) $release->isPreRelease) {
                $this->preRelease = $release;
            } else {
                $this->release = $release;
            }
        }
        if(isset($this->release, $this->preRelease) and $this->preRelease->creation < $this->release->creation) $this->preRelease = null;

        foreach(Mysql::query("SELECT userId, level FROM project_subs WHERE projectId = ? AND level > ?", "ii", $this->project->projectId, ProjectSubToggleAjax::LEVEL_NONE) as $row) {
            $this->subs[(int) $row["userId"]] = (int) $row["level"];
        }
    }

    public function getTitle(): string {
        return htmlspecialchars("$this->projectName ($this->user/$this->repoName)");
    }

    public function output() {
        $projectData = [
            "path" => [$this->user, $this->repoName, $this->projectName],
            "project" => $this->project,
            "release" => $this->release,
            "preRelease" => $this->preRelease,
            "readPerm" => $this->readPerm,
            "writePerm" => $this->writePerm,
            "subs" => $this->subs
        ];
        ?>
      <!--suppress JSUnusedLocalSymbols -->
      <script>var projectData = <?= json_encode($projectData, JSON_UNESCAPED_SLASHES) ?>;</script>
      <div id="ci-pane-container">
        <div id="ci-project-pane">
          <h3 id="ci-project-name">
              <?php if($this->project->projectType === ProjectBuilder::PROJECT_TYPE_PLUGIN) { ?>
                Plugin project:
              <?php } elseif($this->project->projectType === ProjectBuilder::PROJECT_TYPE_LIBRARY) { ?>
                Library project:
              <?php } ?>
              <?= htmlspecialchars($this->project->projectName) ?>
              <?php Mbd::ghLink("https://github.com/$this->user/$this->repoName/tree/master/{$this->project->projectPath}", 20, "projectPath") ?>
          </h3>
          <h5>Basic information <?php Mbd::displayAnchor("project-info") ?></h5>
          <table id="ci-project-info-table">
            <tr>
              <th>Repo</th>
              <td>
                <img src="https://github.com/<?= $this->project->repoOwner ?>.png?size=20" width="20" onerror="this.src='/res/ghMark.png'; this.onerror=null;"/>
                <a href="<?= Meta::root() . "ci/{$this->project->repoOwner}" ?>"><?= $this->project->repoOwner ?></a>
                  <?php Mbd::ghLink("https://github.com/" . $this->project->repoOwner); ?>
                /
                <a href="<?= Meta::root() . "ci/{$this->project->repoOwner}/{$this->project->repoName}" ?>">
                    <?= $this->project->repoName ?></a>
                  <?php Mbd::ghLink("https://github.com/" . $this->project->repoOwner. "/" . $this->project->repoName); ?>
              </td>
            </tr>
            <tr>
              <th>Project framework</th>
              <td>
                  <?php
                  switch($this->project->projectType) {
                      case ProjectBuilder::PROJECT_TYPE_PLUGIN:
                          switch($this->project->projectModel) {
                              case "default":
                                  echo "DevTools style";
                                  break;
                              case "nowhere":
                                  echo "NOWHERE framework ";
                                  Mbd::ghLink("https://github.com/PEMapModder/NOWHERE");
                                  break;
                          }
                          break;
                      case ProjectBuilder::PROJECT_TYPE_LIBRARY:
                          switch($this->project->projectModel) {
                              case "virion":
                                  echo "Virion framework ";
                                  Mbd::ghLink("https://github.com/poggit/support/blob/master/virion.md");
                                  break;
                          }
                          break;
                      case ProjectBuilder::PROJECT_TYPE_SPOON:
                          echo "PocketMine-MP";
                          break;
                  }
                  ?>
              </td>
            </tr>
              <?php if($this->project->projectType === ProjectBuilder::PROJECT_TYPE_LIBRARY) { ?>
                <tr>
                  <th>Latest antigen</th>
                  <td><code><?= htmlspecialchars($this->project->main) ?></code></td>
                </tr>
              <?php } elseif($this->project->projectType === ProjectBuilder::PROJECT_TYPE_PLUGIN) { ?>
                <tr>
                  <th>Latest main class</th>
                    <?php
                    echo "<td><code>";
                    $parts = explode("\\", $this->project->main);
                    echo "<span style='font-weight: bolder;'>";
                    echo htmlspecialchars(implode("\\", array_slice($parts, 0, -1)));
                    echo "</span>";
                    echo htmlspecialchars("\\" . end($parts));
                    echo "</code></td>";
                    ?>
                </tr>
              <?php } ?>
            <tr>
              <th>Poggit Project ID</th>
              <td><?= $this->project->projectId ?></td>
            </tr>
            <tr>
              <th>Subscribers</th>
              <td><?= count($this->subs) ?></td>
            </tr>
              <?php if(Session::getInstance()->isLoggedIn()) { ?>
                <tr>
                  <th>My subscription</th>
                  <td>
                    <select id="select-project-sub">
                        <?php $mySub = $this->subs[Session::getInstance()->getUid()] ?? 0; ?>
                        <?php foreach(ProjectSubToggleAjax::$LEVELS_TO_HUMAN as $level => $human) { ?>
                          <option value="<?= $level ?>"<?= $mySub === $level ? "selected" : "" ?>><?= $human ?></option>
                        <?php } ?>
                    </select>
                    <span id="toggle-project-sub" data-project-id="<?= $this->project->projectId ?>"
                          class="action">Change</span>
                  </td>
                </tr>
              <?php }
              if($this->release && (($this->release->state > Release::STATE_CHECKED) or (Session::getInstance()->isLoggedIn() && ($this->release->state === Release::STATE_CHECKED)) or $this->writePerm)) { ?>
                <tr>
                  <th>Latest Release</th>
                  <td>
                    <a href="<?= Meta::root() ?>p/<?= urlencode($this->release->name) ?>/<?= $this->release->version ?>"><?= urlencode($this->release->name) ?>
                      v<?= $this->release->version ?> build
                      &<?= dechex((int)$this->release->buildId) ?></a><br>Created: <?= htmlspecialchars(date('d M Y', $this->release->creation)) ?>
                  </td>
                </tr>
              <?php }
              if($this->preRelease && (($this->preRelease->state > Release::STATE_CHECKED) or (Session::getInstance()->isLoggedIn() && ($this->preRelease->state === Release::STATE_CHECKED)) or $this->writePerm)) { ?>
                <tr>
                  <th>Latest PreRelease</th>
                  <td>
                    <a href="<?= Meta::root() ?>p/<?= urlencode($this->preRelease->name) ?>/<?= $this->preRelease->version ?>"><?= urlencode($this->preRelease->name) ?>
                      v<?= $this->preRelease->version ?> build &<?= dechex((int)$this->preRelease->buildId) ?></a><br>Created: <?= htmlspecialchars(date('d M Y', $this->preRelease->creation)) ?>
                  </td>
                </tr>
              <?php } ?>
            <!-- TODO badge/shield -->
          </table>
          <h5>Build History <?php Mbd::displayAnchor("project-history") ?></h5>
          <div>
            <select class="ci-project-history-locks" id="ci-project-history-branch-select">
              <!-- TODO select from URL -->
              <option value="special:dev" selected>Dev builds only</option>
                <?php if($this->project->projectId !== 210) { ?>
                  <option value="special:pr">Pull Request builds only (more unstable)</option>
                <?php } ?>
              <optgroup label="Dev builds from branch:">
                  <?php foreach(Mysql::query("SELECT branch, COUNT(internal) cnt, MAX(buildId) maxBuildId
                                FROM builds WHERE projectId = ? AND class = ?
                                GROUP BY branch ORDER BY maxBuildId DESC",
                      "ii", $this->project->projectId, ProjectBuilder::BUILD_CLASS_DEV) as $row) { ?>
                    <option value="<?= Mbd::esq($row["branch"]) ?>">
                        <?= htmlspecialchars($row["branch"] . " ({$row["cnt"]} builds)") ?></option>
                  <?php } ?>
              </optgroup>
            </select>
          </div>
          <div id="ci-project-history-table-wrapper">
            <table id="ci-project-history-table">
              <tr class="ci-project-history-header">
                  <?php if($this->writePerm) { ?>
                    <th>Action</th><?php } ?>
                <th>Build #</th>
                <th>Date</th>
                <th>Lint</th>
                <th>Commit</th>
                <th>Branch or Pull Request number</th>
                <th>Download</th>
                  <?php if($this->project->projectType === ProjectBuilder::PROJECT_TYPE_LIBRARY) { ?>
                    <th>Virion version</th>
                  <?php } ?>
              </tr>
            </table>
          </div>
          <div><span class="action ci-project-history-locks" id="ci-project-history-load-more">
                            Load more builds</span></div>
        </div>
        <div id="ci-build-pane">
          <div id="ci-build-header-floats">
            <h4 id="ci-build-header" style="float: left;"></h4>
            <span id="ci-build-close" class="action" style="float: right;">X</span>
          </div>
          <div id="ci-build-inner" autofocus>
            <h5 class="ci-build-section-title">Initiation <?php Mbd::displayAnchor("build-init") ?></h5>
            <div class="ci-build-loading">Loading...</div>
            <div id="ci-build-init" class="ci-build-section-content">
              <h6>Commit</h6>
              <div>
                <code id="ci-build-sha"></code>
                <span class="ci-build-commit-message"><span class="ci-build-commit-details"></span></span>
              </div>
            </div>
            <div class="ci-build-virion-section">
              <h5 class="ci-build-section-title">Virions used <?php Mbd::displayAnchor("build-virions") ?></h5>
              <div class="ci-build-loading">Loading...</div>
              <ul id="ci-build-virion" class="ci-build-section-content">
              </ul>
            </div>
            <div class="ci-build-download-section">
              <br />
              <h5 class="ci-build-section-title">Downloads</h5>
              <div class="ci-build-loading">Loading...</div>
              <div id="ci-build-download" class="ci-build-section-content">
              </div>
              <br />
            </div>
            <h5 class="ci-build-section-title">Lint <?php Mbd::displayAnchor("build-lint") ?></h5>
            <div class="ci-build-loading">Loading...</div>
            <div id="ci-build-lint" class="ci-build-section-content"></div>
          </div>
        </div>
      </div>
      <div id="wait-spinner" class="loading">Loading...</div>
        <?php
        Module::queueJs("ci.project");
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
