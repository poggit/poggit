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

class ReviewManagement extends AjaxModule {

    protected function impl() {
        // read post fields
        if(!isset($_POST["action"]) || !is_string($_POST["action"])) $this->errorBadRequest("Invalid Parameter");
        if(!isset($_POST["relId"]) || !is_numeric($_POST["relId"])) $this->errorBadRequest("Invalid Parameter");
        //if(!isset($_POST["author"]) || !is_string($_POST["author"])) $this->errorBadRequest("Invalid Parameter");

        $user = SessionUtils::getInstance()->getLogin()["name"] ?? "";
        $userlevel = Poggit::getAdmlv($user);

        switch($_POST["action"]) {

            case "add":
                $uid = OfficialReviewModule::getUIDFromName($user);
                MysqlUtils::query("INSERT INTO release_reviews (releaseId, user, criteria, type, cat, score, message, created) VALUES (?, ? ,? ,? ,? ,? ,?, ?)",
                    "iiiiiisi", $_POST["relId"], $uid, $_POST["criteria"] ?? 0, $_POST["type"], $_POST["category"], $_POST["score"], $_POST["message"], null); // TODO support GFM
                break;

            case "delete" :
                $authorname = $_POST["author"];
                $id = OfficialReviewModule::getUIDFromName($_POST["author"]);
                if(($userlevel >= Poggit::MODERATOR) || ($user == $authorname)) { // Moderators up
                    MysqlUtils::query("DELETE FROM release_reviews WHERE (releaseId = ? AND user = ? AND criteria = ?)",
                        "iii", $_POST["relId"], $id, $_POST["criteria"]);
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
