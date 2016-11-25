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
