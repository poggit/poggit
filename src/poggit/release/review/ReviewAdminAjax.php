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
use poggit\utils\internet\MysqlUtils;

class ReviewAdminAjax extends AjaxModule {
    protected function impl() {
        // read post fields
        if(!isset($_POST["action"]) || !is_string($_POST["action"])) $this->errorBadRequest("Invalid Parameter");
        if(!isset($_POST["relId"]) || !is_numeric($_POST["relId"])) $this->errorBadRequest("Invalid Parameter");

        $user = SessionUtils::getInstance()->getLogin()["name"] ?? "";
        $userlevel = Poggit::getAdmlv($user);
        $useruid = ReviewUtils::getUIDFromName($user);
        $relauthor = MysqlUtils::query("SELECT rep.owner as relauthor FROM repos rep
                INNER JOIN projects p on p.repoId = rep.repoId
                INNER JOIN releases rel on rel.projectId = p.projectId
                WHERE rel.releaseId = ? LIMIT 1",
            "i", $_POST["relId"])[0]["relauthor"];
        switch($_POST["action"]) {
            case "add":
                if($_POST["score"] > 5 || $_POST["score"] < 0 || (strlen($_POST["message"] > 256 && $userlevel < Poggit::MODERATOR)) || ($user == $relauthor)) break;
                MysqlUtils::query("INSERT INTO release_reviews (releaseId, user, criteria, type, cat, score, message, created) VALUES (?, ? ,? ,? ,? ,? ,?, ?)",
                    "iiiiiisi", $_POST["relId"], $useruid, $_POST["criteria"] ?? 0, $_POST["type"], $_POST["category"], $_POST["score"], $_POST["message"], null); // TODO support GFM
                break;
            case "delete" :
                if(!isset($_POST["author"]) || !is_string($_POST["author"])) $this->errorBadRequest("Invalid Parameter");
                $revauthoruid = ReviewUtils::getUIDFromName($_POST["author"]) ?? "";
                if(($userlevel >= Poggit::MODERATOR) || ($useruid == $revauthoruid)) { // Moderators up
                    MysqlUtils::query("DELETE FROM release_reviews WHERE (releaseId = ? AND user = ? AND criteria = ?)",
                        "iii", $_POST["relId"], $revauthoruid, $_POST["criteria"]);
                }
                break;
        }
    }

    public function getName(): string {
        return "review.admin";
    }

    protected function needLogin(): bool {
        return true;
    }
}
