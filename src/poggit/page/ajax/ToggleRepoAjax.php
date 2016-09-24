<?php

/*
 * Copyright 2016 poggit
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

namespace poggit\page\ajax;

use poggit\page\AjaxPage;
use poggit\Poggit;
use poggit\session\SessionUtils;

class ToggleRepoAjax extends AjaxPage {
    protected function impl() {
        if(!isset($_POST["repoId"])) $this->errorBadRequest("Missing post field 'repoId'");
        $repoId = (int) $_POST["repoId"];
        if(!isset($_POST["property"])) $this->errorBadRequest("Missing post field 'property'");
        $property = $_POST["property"];
        if($property === "build") {
            $col = "build";
        } elseif($property === "release") {
            $col = "rel";
        } else {
            $this->errorBadRequest("Unknown property $property");
            die;
        }
        if(!isset($_POST["enabled"])) $this->errorBadRequest("Missing post field 'enabled'");
        $enabled = $_POST["enabled"] === "true" ? 1 : 0;

        $session = SessionUtils::getInstance();
        $repos = Poggit::ghApiGet("https://api.github.com/user/repos", $session->getLogin()["access_token"]);
        foreach($repos as $repoObj) {
            if($repoObj->id === $repoId) {
                $ok = true;
                break;
            }
        }
        if(!isset($ok)) {
            $this->errorBadRequest("Repo of ID $repoId is not owned by " . $session->getLogin()["name"]);
        }
        /** @var \stdClass $repoObj */
        /** @var \mysqli $db */
        if($repoObj->private and $col === "rel") {
            $this->errorBadRequest("Private repos cannot be released!");
        }
        Poggit::queryAndFetch("INSERT INTO repos (repoId, owner, name, private, `$col`, accessWith) VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE `$col` = ?", "issiisi", $repoId, $repoObj->owner->login, $repoObj->name,
            $repoObj->private, $enabled, $session->getLogin()["access_token"], $enabled);
        echo json_encode(["status" => true]);
    }

    public function getName() : string {
        return "toggleRepo";
    }
}
