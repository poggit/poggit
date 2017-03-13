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
use poggit\resource\ResourceManager;
use poggit\utils\internet\MysqlUtils;

class ReleaseManagement extends AjaxModule {

    protected function impl() {
        // read post fields
        if(!isset($_POST["relId"]) || !is_numeric($_POST["relId"])) $this->errorBadRequest("Invalid Parameter");
        if(!isset($_POST["action"]) || !is_string($_POST["action"])) $this->errorBadRequest("Invalid Parameter");

        $user = SessionUtils::getInstance()->getLogin()["name"] ?? "";
        $relId = $_POST["relId"];
        switch($_POST["action"]) {
            case "vote" :
                if(!isset($_POST["points"]) || !is_numeric($_POST["points"])) $this->errorBadRequest("Invalid Parameter");
                if(!isset($_POST["message"]) || !is_string($_POST["message"])) $this->errorBadRequest("Invalid Parameter");
                $points = $_POST["points"];
                $message = $_POST["message"];
                $currstate = MysqlUtils::query("SELECT state FROM releases WHERE releaseId = ?",
                    "i", $relId)[0]["state"];
                if ($currstate != PluginRelease::RELEASE_STAGE_CHECKED) {
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
                MysqlUtils::query("INSERT INTO release_votes (user, releaseId, points, message) VALUES (?, ?, ?, ?)",
                    "iiis", $uid, $relId, $points, $message);
                $allpoints = MysqlUtils::query("SELECT SUM(release_votes.points) AS points FROM release_votes WHERE releaseId = ?", "i", $relId);
                $totalpoints = (count($allpoints) > 0) ? $allpoints[0]["points"] : 0;
                if($totalpoints >= PluginRelease::POINTS_THRESHOLD) {
                    MysqlUtils::query("UPDATE releases SET state = ? WHERE releaseId = ?",
                        "ii", PluginRelease::RELEASE_STAGE_VOTED, $relId);
                    echo json_encode([
                        "state" => PluginRelease::RELEASE_STAGE_VOTED
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
                    Poggit::getLog()->w("$user set releaseId $relId to stage $state");
                    echo json_encode([
                        "state" => $state
                    ]);
                }
                break;

            case "delete" :
                $relMeta = MysqlUtils::query("SELECT rp.owner as owner, r.description AS description, r.changelog AS changelog, r.licenseRes AS licres FROM repos rp
                INNER JOIN projects p ON p.repoId = rp.repoId
                INNER JOIN releases r ON r.projectId = p.projectId
                WHERE r.releaseId = ?", "i", $relId);
                if($user == $relMeta[0]["owner"] || Poggit::getAdmlv($user) === Poggit::ADM) {
                    MysqlUtils::query("DELETE FROM releases WHERE releaseId = ?",
                        "i", $relId);

                    $description = $relMeta[0]["description"];
                    $changelog = $relMeta[0]["changelog"];
                    $licenseres = $relMeta[0]["licres"];

                    $desc = ResourceManager::getInstance()->getResource($description);
                    unlink($desc);
                    if($changelog) {
                        $change = ResourceManager::getInstance()->getResource($changelog);
                        unlink($change);
                    }
                    if($licenseres) {
                        $licres = ResourceManager::getInstance()->getResource($licenseres);
                        unlink($licres);
                    }
                }

                echo json_encode([
                    "state" => -1
                ]);
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
