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

namespace poggit\release\details;

use poggit\account\SessionUtils;
use poggit\module\AjaxModule;
use poggit\release\PluginRelease;
use poggit\utils\Config;
use poggit\utils\internet\CurlUtils;
use poggit\utils\internet\MysqlUtils;

class ReleaseVoteAjax extends AjaxModule {
    public function getName(): string {
        return "release.vote";
    }

    protected function impl() {
        $relId = $this->param("relId");
        $vote = ((int) $this->param("vote")) <=> 0;
        $message = $this->param("message");
        $session = SessionUtils::getInstance();
        if($vote < 0 && strlen($message) < 10) $this->errorBadRequest("Negative vote must contain a message");
        if(strlen($message) > 255) $this->errorBadRequest("Message too long");
        $currState = MysqlUtils::query("SELECT state FROM releases WHERE releaseId = ?", "i", $relId)[0]["state"];
        if($currState != PluginRelease::RELEASE_STATE_CHECKED) $this->errorBadRequest("This release is not in the CHECKED state");
        $currentReleaseDataRows = MysqlUtils::query("SELECT p.repoId, r.state FROM projects p
                INNER JOIN releases r ON r.projectId = p.projectId
                WHERE r.releaseId = ?", "i", $relId);
        if(!isset($currentReleaseDataRows[0])) $this->errorBadRequest("Nonexistent release");
        $currentReleaseData = $currentReleaseDataRows[0];
        if(CurlUtils::testPermission($currentReleaseData["repoId"], $session->getAccessToken(), $session->getName(), "push")) {
            $this->errorBadRequest("You can't vote for your own plugin!");
        }
        $uid = SessionUtils::getInstance()->getUid();
        MysqlUtils::query("DELETE FROM release_votes WHERE user = ? AND releaseId = ?", "ii", $uid, $relId);
        MysqlUtils::query("INSERT INTO release_votes (user, releaseId, vote, message) VALUES (?, ?, ?, ?)", "iiis", $uid, $relId, $vote, $message);
        $allVotes = MysqlUtils::query("SELECT IFNULL(SUM(release_votes.vote), 0) AS votes FROM release_votes WHERE releaseId = ?", "i", $relId);
        $totalVotes = (count($allVotes) > 0) ? $allVotes[0]["votes"] : 0;
        if($voted = $totalVotes >= Config::VOTED_THRESHOLD) {
            // yay, finally vote-approved!
            MysqlUtils::query("UPDATE releases SET state = ? WHERE releaseId = ?", "ii", PluginRelease::RELEASE_STATE_VOTED, $relId);
        }

        echo json_encode(["passed" => $voted]);
    }
}
