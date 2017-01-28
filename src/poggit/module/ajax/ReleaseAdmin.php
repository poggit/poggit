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

namespace poggit\module\ajax;

use poggit\Poggit;
use poggit\resource\ResourceManager;
use poggit\utils\internet\MysqlUtils;
use poggit\utils\SessionUtils;

class ReleaseAdmin extends AjaxModule {

    protected function impl() {
        // read post fields
        if(!isset($_POST["relId"]) || !is_numeric($_POST["relId"])) $this->errorBadRequest("Invalid Parameter");
        if(!isset($_POST["state"]) || !is_numeric($_POST["state"])) $this->errorBadRequest("Invalid Parameter");
        if(!isset($_POST["action"]) || !is_string($_POST["action"])) $this->errorBadRequest("Invalid Parameter");

        $user = SessionUtils::getInstance()->getLogin()["name"] ?? "";
        switch ($_POST["action"]) {
            case "update" :
                if (Poggit::getAdmlv($user) === Poggit::ADM) {
                    $relId = $_POST["relId"];
                    $state = $_POST["state"];
                    MysqlUtils::query("UPDATE releases SET state = ? WHERE releaseId = ?",
                        "ii", $state, $relId);
                    Poggit::getLog()->i("$user set releaseId $relId to stage $state");
                }
                echo json_encode([
                    "state" => $state
                ]);
                break;

            case "delete" :
                $relId = $_POST["relId"];
                $relMeta = MysqlUtils::query("SELECT rp.owner as owner, r.description AS description, r.changelog AS changelog, r.licenseRes AS licres FROM repos rp
                INNER JOIN projects p ON p.repoId = rp.repoId
                INNER JOIN releases r ON r.projectId = p.projectId
                WHERE r.releaseId = ?","i", $relId);
                if ($user == $relMeta[0]["owner"] || Poggit::getAdmlv($user) === Poggit::ADM) {
                    MysqlUtils::query("DELETE FROM releases WHERE releaseId = ?",
                        "i", $relId);
                }
                // TODO remove other DB stuff: deps, meta, perms, reqs etc ?
                $description = $relMeta[0]["description"];
                $changelog = $relMeta[0]["changelog"];
                $licenseres = $relMeta[0]["licres"];

                $desc = ResourceManager::getInstance()->getResource($description);
                unlink($desc);
                if ($changelog) {
                $change = ResourceManager::getInstance()->getResource($changelog);
                unlink($change);
                }
                if ($licenseres) {
                $licres = ResourceManager::getInstance()->getResource($licenseres);
                unlink($licres);
                }

                MysqlUtils::query("DELETE FROM resources WHERE resourceId IN (?, ?, ?)","iii", $description, $changelog, $licenseres);
                MysqlUtils::query("DELETE FROM release_deps WHERE releaseId = ?","i", $relId);
                MysqlUtils::query("DELETE FROM release_meta WHERE releaseId = ?","i", $relId);
                MysqlUtils::query("DELETE FROM release_perms WHERE releaseId = ?","i", $relId);
                MysqlUtils::query("DELETE FROM release_reqr WHERE releaseId = ?","i", $relId);
                MysqlUtils::query("DELETE FROM release_reviews WHERE releaseId = ?","i", $relId);
                MysqlUtils::query("DELETE FROM release_spoons WHERE releaseId = ?","i", $relId);

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
