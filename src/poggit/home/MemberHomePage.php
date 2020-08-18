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

namespace poggit\home;

use poggit\account\Session;
use poggit\ci\builder\ProjectBuilder;
use poggit\ci\ui\ProjectThumbnail;
use poggit\Config;
use poggit\japi\ci\BuildInfoApi;
use poggit\Mbd;
use poggit\Meta;
use poggit\module\VarPage;
use poggit\release\Release;
use poggit\timeline\TimeLineEvent;
use poggit\utils\internet\GitHub;
use poggit\utils\internet\Mysql;
use poggit\utils\PocketMineApi;
use function array_map;
use function count;
use function explode;
use function htmlspecialchars;
use function implode;
use function in_array;
use function json_decode;
use function urlencode;
use const poggit\ASSETS_PATH;

class MemberHomePage extends VarPage {
    private $projects;

    /** @var array[] */
    private $timeline;

    private $recentBuilds;
    private $repos;
    private $username;
    /** @var int */
    private $newReleases;

    public function __construct() {
        $session = Session::getInstance();
        $this->username = $session->getName();
        $ids = [];
        foreach($repos = GitHub::listMyRepos($session->getAccessToken()) as $repo) {
            /** @noinspection NullPointerExceptionInspection */
            $ids[] = "p.repoId=" . (int) $repo->id;
        }
        $where = "(" . implode(" OR ", $ids) . ")";
        foreach(count($ids) === 0 ? [] : Mysql::query("SELECT r.repoId AS rid, p.projectId AS pid, p.name AS projectName,
        (SELECT UNIX_TIMESTAMP(created) FROM builds WHERE builds.projectId=p.projectId 
                        AND builds.class IS NOT NULL ORDER BY created DESC LIMIT 1) AS buildDate,
                (SELECT COUNT(*) FROM builds WHERE builds.projectId=p.projectId 
                        AND builds.class IS NOT NULL) AS buildCnt,
                IFNULL((SELECT CONCAT_WS(',', buildId, internal) FROM builds WHERE builds.projectId = p.projectId
                        AND builds.class = ? ORDER BY created DESC LIMIT 1), 'null') AS buildNumber
                FROM projects p INNER JOIN repos r ON p.repoId=r.repoId WHERE r.build=1 AND $where ORDER BY buildDate DESC", "i", ProjectBuilder::BUILD_CLASS_DEV) as $projRow) {
            $repo = $repos[(int) $projRow["rid"]] ?? null;
            if($repo === null || in_array($repo, $this->repos ?? [], true)) continue;
            $project = new ProjectThumbnail();
            $project->id = (int) $projRow["pid"];
            $project->name = $projRow["projectName"];
            $project->buildCount = (int) $projRow["buildCnt"];
            $project->buildDate = $projRow["buildDate"];
            if($projRow["buildNumber"] === "null") {
                $project->latestBuildGlobalId = null;
                $project->latestBuildInternalId = null;
            } else {
                list($project->latestBuildGlobalId, $project->latestBuildInternalId) = array_map("intval", explode(",", $projRow["buildNumber"]));
            }
            $project->repo = $repo;
            $repo->projects[] = $project;
            $this->repos[] = $repo;
        }

        $this->timeline = Mysql::query("SELECT e.eventId, UNIX_TIMESTAMP(e.created) AS created, e.type, e.details 
            FROM user_timeline u INNER JOIN event_timeline e ON u.eventId = e.eventId
            WHERE u.userId = ? ORDER BY e.created DESC LIMIT 50", "i", $session->getUid());

        $buildApi = new BuildInfoApi;
        foreach($this->timeline as $key => $value) {
            if($value["type"] === TimeLineEvent::EVENT_BUILD_COMPLETE) {
                if(isset($value["details"]["buildId"])) {
                    $this->timeline[$key]["buildId"] = $buildApi->process(json_decode($value["details"]))->buildId;
                }
            }
        }

        $lastNotif = $session->getLastNotif();
        $this->newReleases = (int) Mysql::query("SELECT COUNT(DISTINCT projectId) cnt FROM releases WHERE UNIX_TIMESTAMP(updateTime) > ? AND state >= ?", "ii", $lastNotif, Config::MIN_PUBLIC_RELEASE_STATE)[0]["cnt"];
    }

    protected function thumbnailProject(ProjectThumbnail $project) {
        ?>
      <div class="brief-info" data-project-id="<?= $project->id ?>">

        <a href="<?= Meta::root() ?>ci/<?= $project->repo->full_name ?>/<?= urlencode($project->name) ?>">
            <?= htmlspecialchars($project->name) ?>
        </a>
        <div class="remark">Total: <?= $project->buildCount ?> development
          build<?= $project->buildCount > 1 ? "s" : "" ?></div>
        <div class="remark">Latest: <span class="time-elapse" data-timestamp="<?= $project->buildDate ?>"></span>
        </div>
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

    public function bodyClasses(): array {
        return ["horiz-panes"];
    }

    public function getTitle(): string {
        return "Poggit";
    }

    public function output() {
        // $simpleStats = new SimpleStats();
        ?>
      <div class="member-panel-plugins">
        <div class="recent-builds-header"><a href="<?= Meta::root() ?>ci/recent"><h4>Latest Releases</h4></a>
        </div>
        <div class="recent-builds-wrapper">
            <?php Release::showRecentPlugins(10); ?>
        </div>
      </div>
      <div class="member-panel-timeline">
        <h1 class="motto">Developer Dashboard</h1>
        <div id="home-timeline" class="timeline">
            <?php if($this->newReleases > 0) { ?>
              <div class="alert alert-warning alert-dismissible"
                   role="alert"><?= $this->newReleases > 1 ? "$this->newReleases plugins have" : "1 plugin has" ?> been
                released/updated since
                <span class="time" data-timestamp="<?= Session::getInstance()->getLastNotif() ?>"></span>.
                  <span class="action" onclick="homeBumpNotif()"><nobr>Check them out</nobr></span>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close"
                        onclick="homeBumpNotif(false)"><span aria-hidden="true">&times;</span></button>
              </div>
            <?php } ?>
          <ul>
            <li><a href="#home-timeline-1">Activity</a></li>
            <li><a href="#home-timeline-2">Subscriptions</a></li>
            <li><a href="#home-timeline-3">Poggit Stats</a></li>
          </ul>
          <div id="home-timeline-1">
            <div class="account-tab">
                <?php foreach($this->timeline as $event) {
                    if($event["type"] === TimeLineEvent::EVENT_WELCOME) { ?>
                      <div class="timeline-event">
                          <?php
                          TimeLineEvent::fromJson((int) $event["eventId"], (int) $event["created"], (int) $event["type"], json_decode($event["details"]))->output();
                          ?>
                      </div>
                    <?php }
                } ?>
            </div>
          </div>
          <div id="home-timeline-2">
            <div class="subs-tab">
                <?php foreach($this->timeline as $event) {
                    if($event["type"] === TimeLineEvent::EVENT_BUILD_COMPLETE) { ?>
                      <div class="timeline-event">
                          <?php
                          TimeLineEvent::fromJson((int) $event["eventId"], (int) $event["created"], (int) $event["type"], json_decode($event["details"]))->output();
                          ?>
                      </div>
                    <?php }
                } ?>
            </div>
          </div>
          <div id="home-timeline-3">
<?php /*
            <div class="brief-info" id="home-stats">
              <p>Users registered: <?= $simpleStats->users ?></p>
              <p>Repos integrated: <?= $simpleStats->repos ?></p>
              <p>Plugin Projects created: <?= $simpleStats->pluginProjects ?></p>
              <p>Plugin Builds created: <?= $simpleStats->pluginBuilds ?></p>
              <p>Virion Projects created: <?= $simpleStats->virionProjects ?></p>
              <p>Virion Builds created: <?= $simpleStats->virionBuilds ?></p>
              <p>Released plugins (at least one version <em>Voted</em> or above): <?= $simpleStats->releases ?></p>
              <p>Compatible released plugins (at least one version <em>Voted</em> or above,
                compatible with <?= PocketMineApi::$LATEST_COMPAT ?>): <?= $simpleStats->compatibleReleases ?></p>
              <p>Total released plugin downloads: <?= $simpleStats->pluginDownloads ?></p>
              <p>Number of IP addresses visiting Poggit: <?= $simpleStats->visitingIps ?></p>
            </div>
 */ ?>
          </div>
        </div>
        <div class="member-main-content">
          <h2 class="motto">Concentrate on your code.<br/>Leave the dirty work to the machines.</h2>
            <?php include ASSETS_PATH . "incl/home.common.php"; ?>
        </div>
      </div>

        <?php
        if(isset($this->repos)) {
            $i = 0;
            ?>
          <div class="member-panel-projects">
            <div class="recent-builds-header"><a href="<?= Meta::root() ?>ci/<?= $this->username ?>"><h4>
                  Your projects</h4></a></div>
            <div class="recent-builds-wrapper">
                <?php
                // loop_repos
                foreach($this->repos as $repo) {
                    if(count($repo->projects) === 0) continue;
                    foreach($repo->projects as $project) {
                        if(++$i > 20) break 2; // loop_repos
                        $this->thumbnailProject($project);
                    }
                } ?>
            </div>
          </div>
            <?php
        }
    }
}
