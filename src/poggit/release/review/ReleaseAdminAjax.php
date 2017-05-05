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

namespace poggit\release\review;

use poggit\account\SessionUtils;
use poggit\module\AjaxModule;
use poggit\Poggit;
use poggit\release\PluginRelease;
use poggit\utils\internet\MysqlUtils;

class ReleaseAdminAjax extends AjaxModule {

    protected function impl() {
        // read post fields
        if(!isset($_POST["relId"]) || !is_numeric($_POST["relId"])) $this->errorBadRequest("Invalid Parameter");
        if(!isset($_POST["action"]) || !is_string($_POST["action"])) $this->errorBadRequest("Invalid Parameter");

        $user = SessionUtils::getInstance()->getLogin()["name"] ?? "";
        $relId = $_POST["relId"];
        switch($_POST["action"]) {
            case "vote" :
                if(!isset($_POST["vote"]) || !is_numeric($_POST["vote"])) $this->errorBadRequest("Invalid Parameter");
                $vote = $_POST["vote"] <=> 0;
                if(!isset($_POST["message"]) || !is_string($_POST["message"]) || ($vote < 0 && strlen($_POST["message"]) < 10)) $this->errorBadRequest("Invalid Parameter");
                $message = $_POST["message"];
                $currstate = MysqlUtils::query("SELECT state FROM releases WHERE releaseId = ?",
                    "i", $relId)[0]["state"];
                if($currstate != PluginRelease::RELEASE_STATE_CHECKED) {
                    echo json_encode([
                        "state" => -1
                    ]);
                    break;
                }
                $relMeta = MysqlUtils::query("SELECT rp.owner as owner FROM repos rp
                INNER JOIN projects p ON p.repoId = rp.repoId
                INNER JOIN releases r ON r.projectId = p.projectId
                WHERE r.releaseId = ?", "i", $relId);
                if($user == $relMeta[0]["owner"]) {
                    echo json_encode([
                        "state" => -1
                    ]);
                    break;
                }
                $uid = SessionUtils::getInstance()->getLogin()["uid"] ?? 0;
                MysqlUtils::query("DELETE FROM release_votes WHERE user=? AND releaseId=?",
                    "ii", $uid, $relId);
                MysqlUtils::query("INSERT INTO release_votes (user, releaseId, vote, message) VALUES (?, ?, ?, ?)",
                    "iiis", $uid, $relId, $vote, $message);
                $allvotes = MysqlUtils::query("SELECT SUM(release_votes.vote) AS votes FROM release_votes WHERE releaseId = ?", "i", $relId);
                $totalvotes = (count($allvotes) > 0) ? $allvotes[0]["votes"] : 0;
                if($totalvotes >= PluginRelease::VOTED_THRESHOLD) {
                    MysqlUtils::query("UPDATE releases SET state = ? WHERE releaseId = ?",
                        "ii", PluginRelease::RELEASE_STATE_VOTED, $relId);
                    echo json_encode([
                        "state" => PluginRelease::RELEASE_STATE_VOTED
                    ]);
                } else {
                    echo json_encode([
                        "state" => -1
                    ]);
                }
                break;

            case "update" :
                if(!isset($_POST["state"]) || !is_numeric($_POST["state"])) $this->errorBadRequest("Invalid Parameter");
                if(Poggit::getAdmlv($user) >= Poggit::MODERATOR) {
                    $state = $_POST["state"];
                    MysqlUtils::query("UPDATE releases SET state = ? WHERE releaseId = ?",
                        "ii", $state, $relId);
                    Poggit::getLog()->w("$user set releaseId $relId to state $state");
                    echo json_encode([
                        "state" => $state
                    ]);
                }
                break;
        }

    }

    public function getName(): string {
        return "release.admin";
    }

    protected function needLogin(): bool {
        return true;
    }
}
