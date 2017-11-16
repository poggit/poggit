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

use poggit\account\Session;
use poggit\Config;
use poggit\module\AjaxModule;
use poggit\release\Release;
use poggit\utils\internet\Curl;
use poggit\utils\internet\Mysql;

class ReleaseVoteAjax extends AjaxModule {
    public function getName(): string {
        return "release.vote";
    }

    protected function impl() {
        $relId = $this->param("relId");
        $vote = ((int) $this->param("vote")) <=> 0;
        $message = $this->param("message");
        $session = Session::getInstance();
        if($vote < 0 && strlen($message) < 10) $this->errorBadRequest("Negative vote must contain a message");
        if(strlen($message) > 255) $this->errorBadRequest("Message too long");
        $currState = (int) Mysql::query("SELECT state FROM releases WHERE releaseId = ?", "i", $relId)[0]["state"];
        if($currState !== Release::STATE_CHECKED) $this->errorBadRequest("This release is not in the CHECKED state");
        $currentReleaseDataRows = Mysql::query("SELECT p.repoId, r.state FROM projects p
                INNER JOIN releases r ON r.projectId = p.projectId
                WHERE r.releaseId = ?", "i", $relId);
        if(!isset($currentReleaseDataRows[0])) $this->errorBadRequest("Nonexistent release");
        $currentReleaseData = $currentReleaseDataRows[0];
        if(Curl::testPermission($currentReleaseData["repoId"], $session->getAccessToken(), $session->getName(), "push")) {
            $this->errorBadRequest("You can't vote for your own plugin!");
        }
        $uid = Session::getInstance()->getUid();
        Mysql::query("DELETE FROM release_votes WHERE user = ? AND releaseId = ?", "ii", $uid, $relId);
        Mysql::query("INSERT INTO release_votes (user, releaseId, vote, message) VALUES (?, ?, ?, ?)", "iiis", $uid, $relId, $vote, $message);
        $allVotes = Mysql::query("SELECT IFNULL(SUM(release_votes.vote), 0) AS votes FROM release_votes WHERE releaseId = ?", "i", $relId);
        $totalVotes = (count($allVotes) > 0) ? $allVotes[0]["votes"] : 0;
        if($voted = ($totalVotes >= Config::VOTED_THRESHOLD)) {
            // yay, finally vote-approved!
            Mysql::query("UPDATE releases SET state = ? WHERE releaseId = ?", "ii", Release::STATE_VOTED, $relId);

            ReleaseStateChangeAjax::notifyRelease($relId, Release::STATE_CHECKED, Release::STATE_VOTED);
        }

        echo json_encode(["passed" => $voted]);
    }
}
