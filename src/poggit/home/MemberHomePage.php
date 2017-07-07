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

namespace poggit\home;

use poggit\account\Session;
use poggit\ci\builder\ProjectBuilder;
use poggit\ci\ui\ProjectThumbnail;
use poggit\japi\ci\BuildInfoApi;
use poggit\Mbd;
use poggit\Meta;
use poggit\module\VarPage;
use poggit\timeline\TimeLineEvent;
use poggit\utils\internet\Curl;
use poggit\utils\internet\Mysql;

class MemberHomePage extends VarPage {
    private $projects;

    /** @var array[] */
    private $timeline;

    private $recentBuilds;
    private $repos;
    private $username;

    public function __construct() {
        $session = Session::getInstance();
        $this->username = $session->getName();
        $repos = [];
        $ids = [];
        foreach(Curl::ghApiGet("user/repos?per_page=" . Meta::getCurlPerPage(), $session->getAccessToken()) as $repo) {
            $repos[(int) $repo->id] = $repo;
            $ids[] = "p.repoId=" . (int) $repo->id;
        }
        $where = "(" . implode(" OR ", $ids) . ")";
        foreach(count($ids) === 0 ? [] : Mysql::query("SELECT r.repoId AS rid, p.projectId AS pid, p.name AS pname,
        (SELECT UNIX_TIMESTAMP(created) FROM builds WHERE builds.projectId=p.projectId 
                        AND builds.class IS NOT NULL ORDER BY created DESC LIMIT 1) AS builddate,
                (SELECT COUNT(*) FROM builds WHERE builds.projectId=p.projectId 
                        AND builds.class IS NOT NULL) AS bcnt,
                IFNULL((SELECT CONCAT_WS(',', buildId, internal) FROM builds WHERE builds.projectId = p.projectId
                        AND builds.class = ? ORDER BY created DESC LIMIT 1), 'null') AS bnum
                FROM projects p INNER JOIN repos r ON p.repoId=r.repoId WHERE r.build=1 AND $where ORDER BY r.name, pname", "i", ProjectBuilder::BUILD_CLASS_DEV) as $projRow) {
            $repo = isset($repos[(int) $projRow["rid"]]) ? $repos[(int) $projRow["rid"]] : null;
            if(is_null($repo) || in_array($repo, $this->repos ?? [])) continue;
            $project = new ProjectThumbnail();
            $project->id = (int) $projRow["pid"];
            $project->name = $projRow["pname"];
            $project->buildCount = (int) $projRow["bcnt"];
            $project->buildDate = $projRow["builddate"];
            if($projRow["bnum"] === "null") {
                $project->latestBuildGlobalId = null;
                $project->latestBuildInternalId = null;
            } else {
                list($project->latestBuildGlobalId, $project->latestBuildInternalId) = array_map("intval", explode(",", $projRow["bnum"]));
            }
            $project->repo = $repo;
            $repo->projects[] = $project;
            $this->repos[] = $repo;
        }

        $this->timeline = Mysql::query("SELECT e.eventId, UNIX_TIMESTAMP(e.created) AS created, e.type, e.details 
            FROM user_timeline u INNER JOIN event_timeline e ON u.eventId = e.eventId
            WHERE u.userId = ? ORDER BY e.created DESC LIMIT 50", "i", $session->getUid());

        $buildapi = new BuildInfoApi;
        foreach($this->timeline as $key => $value) {
            if($value["type"] === TimeLineEvent::EVENT_BUILD_COMPLETE) {
                if(isset($value["details"]["buildId"])) {
                    $this->timeline[$key]["buildId"] = $buildapi->process(json_decode($value["details"]))->buildId;
                }
            }
        }

        $builds = Mysql::query("SELECT b.projectId, p.name AS projectName, b.buildId, b.internal, b.class, UNIX_TIMESTAMP(b.created) AS created,
            r.owner, r.name AS repoName
            FROM builds b
            INNER JOIN projects p ON b.projectId = p.projectId
            INNER JOIN repos r ON r.repoId = p.repoId
            WHERE b.buildId IN (SELECT MAX(e.buildId) FROM builds e GROUP BY e.projectId) 
            AND class = ? AND private = 0 AND r.build > 0 ORDER BY created DESC LIMIT 20", "i", ProjectBuilder::BUILD_CLASS_DEV);
        $recentBuilds = [];
        foreach($builds as $row) {
            $row["buildId"] = (int) $row["buildId"];
            $row["internal"] = (int) $row["internal"];
            $row["class"] = (int) $row["class"];
            $row["created"] = (int) $row["created"];
            $recentBuilds[] = $row;
        }
        $this->recentBuilds = $recentBuilds;
    }

    protected function thumbnailProject(ProjectThumbnail $project, $class = "brief-info") {
        ?>
        <div class="<?= $class ?>" data-project-id="<?= $project->id ?>">

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
        ?>
        <div class="memberpanelplugins">
            <div class="recentbuildsheader"><a href="<?= Meta::root() ?>ci/recent"><h4>Recent Builds</h4></a>
            </div>
            <div class="recentbuildswrapper">
                <?php
                foreach($this->recentBuilds as $build) {
                    $truncatedName = htmlspecialchars(substr($build["projectName"], 0, 14) . (strlen($build["projectName"]) > 14 ? "..." : ""));
                    ?>
                    <div class="brief-info">
                        <a href="<?= Meta::root() ?>ci/<?= $build["owner"] ?>/<?= $build["projectName"] ?>/<?= $build["projectName"] ?>/<?= (ProjectBuilder::$BUILD_CLASS_HUMAN[$build["class"]] . ":" ?? "") . $build["internal"] ?>">
                            <?= htmlspecialchars($truncatedName) ?></a>
                        <p class="remark">
                            <span class="remark">(<?= $build["owner"] ?>/<?= $build["repoName"] ?>)</span></p>
                        <p class="remark"><?= ProjectBuilder::$BUILD_CLASS_HUMAN[$build["class"]] ?> Build
                            #<?= $build["internal"] ?></p>
                        <p class="remark">Created <span class="time-elapse"
                                                        data-timestamp="<?= $build["created"] ?>"></span> ago
                        </p>
                    </div>
                <?php } ?>
            </div>
        </div>
        <div class="memberpaneltimeline">
            <h1 class="motto">Build Your Projects</h1>
            <h2 class="submotto">Create builds the moment you push to GitHub.</h2>
            <p>Poggit CI sets up webhooks in your GitHub repos that link to Poggit. When you push a commit to your repo,
                Poggit creates a development build. When you receive pull requests, Poggit also creates PR builds,
                so you can test the pull request by downloading a build from Poggit CI directly.</p>
            <p>You can 'Disable'
                a repo in CI to pause automatic builds on commit and remove repos and builds from public view on Poggit;
                please note
                that your public releases will still be visible unless you save them as drafts.</p>
            <p>When you "Enable" a repo, Poggit will prompt you to create a poggit.yml file that is used to store the
                settings Poggit
                needs to manage the repo. The default is usually fine, but you should add an icon (see 'Help') if you
                plan to release
                your plugin to the main plugin "Release" section. Other settings in poggit.yml can be used for more
                advanced configuration.
                For example, several plugin frameworks are supported - currently, the normal one with a <code
                        class="code">plugin.yml</code>, and the NOWHERE framework, can be used.</p>
            <div id="tabs" class="timeline">
                <ul>
                    <li><a href="#tabs-1">Subscribed Projects</a></li>
                    <li><a href="#tabs-2">Account</a></li>
                </ul>
                <div id="tabs-1">
                    <?php foreach($this->timeline as $event) {
                        if($event["type"] == TimeLineEvent::EVENT_BUILD_COMPLETE) { ?>
                            <div class="timeline-event">
                                <?php
                                TimeLineEvent::fromJson((int) $event["eventId"], (int) $event["created"], (int) $event["type"], json_decode($event["details"]))->output();
                                ?>
                            </div>
                        <?php }
                    } ?>
                </div>
                <div id="tabs-2">
                    <?php foreach($this->timeline as $event) {
                        if($event["type"] == TimeLineEvent::EVENT_WELCOME) { ?>
                            <div class="timeline-event">
                                <?php
                                TimeLineEvent::fromJson((int) $event["eventId"], (int) $event["created"], (int) $event["type"], json_decode($event["details"]))->output();
                                ?>
                            </div>
                        <?php }
                    } ?>
                </div>
            </div>
            <p>
            <h1 class="motto">Lint for PocketMine Plugins</h1></p>
            <h2 class="submotto">Check pull requests before you merge them.</h2>
            <p>After Poggit CI creates a build for your project it will also execute lint on it. Lint is
                a tool that automatically checks if your code has problems and provides suggestions on how to fix them.
                See <a
                        href="<?= Meta::root() ?>help.lint">Poggit Help: Lint</a> for what the lint checks.
            </p>
            <p>You can check out the lint result on the Poggit Build page. The lint result will also be uploaded to
                GitHub, in the form of status checks, which will do
                <a target="_blank" href="<?= Meta::root() ?>ghhst">many cool things</a>.</p>
            <p class="remark">Note: Poggit cannot test the builds for you, but there is a script that you can put into
                your <a href="https://docs.travis-ci.com/user/getting-started/">Travis-CI</a> build, which will wait for
                and then download builds from Poggit for testing.</p>

            <br/>
            <h1 class="motto">Concentrate on your code.<br/> Leave the dirty work to the machines.</h1>
            <h2 class="submotto">Automatic development builds with lint tailored for
                PocketMine plugins.
            </h2>
            <p class="submotto">Why does Poggit exist? Simply to stop a situation from the web comic
                <a target="_blank" href="https://xkcd.com/1319"><em>xkcd</em></a> from happening.</p>
            <hr/>
            <h4>Coming Soon!</h4>
            <p>An online language manager is currently planned: after you push some language files to your repo, there
                will be a webpage for online translation where other people can help you translate your plugin to other
                languages. The poglang library will then be compiled with your plugin, along with some language files
                contributed by the community.</p>
            <hr/>
        </div>

        <?php
        if(isset($this->repos)) {
            $i = 0;
            ?>
            <div class="memberpanelprojects">
                <div class="recentbuildsheader"><a href="<?= Meta::root() ?>ci/<?= $this->username ?>"><h4>My
                            projects</h4></a></div>
                <?php
                // loop_repos
                foreach($this->repos as $repo) {
                    if(count($repo->projects) === 0) continue;
                    foreach($repo->projects as $project) {
                        if(++$i > 20) break 2; // loop_repos
                        $this->thumbnailProject($project, "brief-info");
                    }
                } ?>
            </div>
            <?php
        }
    }
}
