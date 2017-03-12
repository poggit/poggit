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
        switch($_POST["action"]) {
            case "vote" :
                $relId = $_POST["relId"];
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
                MysqlUtils::query("INSERT INTO release_votes (user, releaseId, approved) VALUES (?, ?, ?)
                                       ON DUPLICATE KEY UPDATE approved = NOT approved, updated = CURRENT_TIMESTAMP",
                    "iii", $uid, $relId, true);
                $allvotes = MysqlUtils::query("SELECT SUM(release_votes.approved) AS votes FROM release_votes WHERE releaseId = ?", "i", $relId);
                $totalvotes = (count($allvotes) > 0) ? $allvotes[0]["votes"] : 0;
                $currstate = MysqlUtils::query("SELECT state FROM releases WHERE releaseId = ?",
                    "i", $relId)[0]["state"];
                if($totalvotes >= PluginRelease::VOTE_THRESHOLD && $currstate == PluginRelease::RELEASE_STAGE_CHECKED) {
                    MysqlUtils::query("UPDATE releases SET state = ? WHERE releaseId = ?",
                        "ii", PluginRelease::RELEASE_STAGE_VOTED, $relId);
                    echo json_encode([
                        "state" => PluginRelease::RELEASE_STAGE_VOTED
                    ]);
                } elseif ($totalvotes < PluginRelease::VOTE_THRESHOLD && $currstate == PluginRelease::RELEASE_STAGE_VOTED) {
                    MysqlUtils::query("UPDATE releases SET state = ? WHERE releaseId = ?",
                        "ii", PluginRelease::RELEASE_STAGE_CHECKED, $relId);
                    echo json_encode([
                        "state" => PluginRelease::RELEASE_STAGE_CHECKED
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
                    $relId = $_POST["relId"];
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
                $relId = $_POST["relId"];
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
