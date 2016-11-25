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

namespace poggit\module\home;

use poggit\module\VarPage;
use poggit\Poggit;
use poggit\session\SessionUtils;
use poggit\timeline\TimeLineEvent;

class MemberHomePage extends VarPage {
    /** @var array[] */
    private $timeline;
    /** @var array[] */
    private $projects;
    private $recentBuilds;

    public function __construct() {
        $session = SessionUtils::getInstance();
        $repos = [];
        foreach(Poggit::ghApiGet("user/repos?per_page=75", $session->getAccessToken()) as $repo) {
            $repos[(int) $repo->id] = $repo;
        }
        $repoIdClause = implode(",", array_keys($repos));
        $this->timeline = Poggit::queryAndFetch("SELECT e.eventId, UNIX_TIMESTAMP(e.created) AS created, e.type, e.details 
            FROM user_timeline u INNER JOIN event_timeline e ON u.eventId = e.eventId
            WHERE u.userId = ? ORDER BY e.created DESC LIMIT 50",
            "i", $session->getLogin()["uid"]);
        $this->projects = Poggit::queryAndFetch("SELECT r.repoId, p.projectId, p.name
            FROM projects p INNER JOIN repos r ON p.repoId = r.repoId 
            WHERE r.build = 1 AND p.projectId IN ($repoIdClause)");
        
        $this->recentBuilds = array_map(function ($row) {
            $row["buildId"] = (int) $row["buildId"];
            $row["internal"] = (int) $row["internal"];
            $row["class"] = (int) $row["class"];
            $row["created"] = (int) $row["created"];
            return $row;
        }, Poggit::queryAndFetch("SELECT b.buildId, b.internal, b.class, UNIX_TIMESTAMP(b.created) AS created, b.status,
            r.owner, r.name AS repoName, p.name AS projectName
            FROM builds b INNER JOIN projects p ON b.projectId = p.projectId INNER JOIN repos r ON p.repoId = r.repoId
            WHERE class = ? AND private = 0 ORDER BY created DESC LIMIT 10", "i", Poggit::BUILD_CLASS_DEV));
    
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
            <h4>Recent builds</h4>
            <?php
            foreach($this->recentBuilds as $build) {
                $permLink = dechex((int) $build["buildId"]);
                ?>
                <div class="brief-info">
                    <p class="recentbuildbox">
                        <a href="<?= Poggit::getRootPath() ?>ci/<?= $build["owner"] ?>/<?= $build["repoName"] ?>">
                            <?= htmlspecialchars($build["projectName"]) ?></a> &amp;<?= $permLink ?><br/>
                        <span class="remark">(<?= $build["owner"] ?>/<?= $build["repoName"] ?>)<br/>
                            <?= Poggit::$BUILD_CLASS_HUMAN[$build["class"]] ?> Build #<?= $build["internal"] ?><br/>
                        Created <span class="time-elapse" data-timestamp="<?= $build["created"] ?>"></span> ago</span>
                    </p>
                </div>
            <?php } ?>
        </div>
        <div class="memberpaneltimeline">
            
            <h1 class="motto">Concentrate on your code.<br/> Leave the dirty work to the machines.</h1>
            <h2 class="submotto">Automatic development builds with lint tailored for
                PocketMine plugins.<br/>
                </h2>
            <p class="submotto">Why does Poggit exist? Simply to stop this situation from the web comic
                <a href="https://xkcd.com/1319"><em>xkcd</em></a> from happening.<br/>
                <a href="https://xkcd.com/1319">
                    <img class="resize" src="https://imgs.xkcd.com/comics/automation.png"/></a></p>
            <hr/>
            <h1 class="motto">Build your projects</h1>
            <h2 class="submotto">Create builds the moment you push to GitHub.</h2>
            <p>Poggit CI will set up webhooks in your repos to link to Poggit. When you push a commit to your repo,
                Poggit will create a development build. When you receive pull requests, Poggit also creates PR builds,
                so you can test the pull request by downloading a build from Poggit CI directly.</p>
            <p>Different plugin frameworks are supported. Currently, the normal one with a <code
                        class="code">plugin.yml</code>, and the NOWHERE framework, can be used.</p>
            <p>An online language manager can also be enabled. After you push some language files to your repo, there
                will be a webpage for online translator, and other people can help you translate your plugin to other
                languages. Then the poglang library will be compiled with your plugin, along with some language files
                contributed by the community.</p>
            <hr/>
            <h1 class="motto">Lint for PocketMine plugins</h1>
            <h2 class="submotto">Checks pull request before you can merge them.</h2>
            <p>After Poggit CI creates a build for your project, it will also execute lint on it. Basically, lint is
                something that checks if your code is having problems. See <a
                        href="<?= Poggit::getRootPath() ?>help.lint">Poggit Help: Lint</a> for what the lint checks.
            </p>
            <p>You can check out the lint result on the Poggit Build page. The lint result will also be uploaded to
                GitHub, in the form of status checks, which will do
                <a target="_blank" href="<?= Poggit::getRootPath() ?>ghhst">many cool things</a>.</p>
            <p class="remark">Note: Poggit cannot test the builds for you, but there is a script that you can put into
                your <a href="https://docs.travis-ci.com/user/getting-started/">Travis-CI</a> build, which will wait for
                and then download builds from Poggit for testing.</p>
            
            <div class="timeline">
                <?php foreach($this->timeline as $event) { ?>
                    <div class="timeline-event">
                        <?php TimeLineEvent::fromJson((int) $event["eventId"], (int) $event["created"], (int) $event["type"], json_decode($event["details"]))->output() ?>
                    </div>
                <?php } ?>
            </div>
        </div>
        <div class="memberpanelprojects">
            <h3>My projects</h3>
        </div>
        <?php
    }
}
