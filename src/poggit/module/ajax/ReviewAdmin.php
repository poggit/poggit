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
use poggit\utils\internet\MysqlUtils;
use poggit\utils\SessionUtils;
use poggit\module\releases\review\OfficialReviewModule;

class ReviewAdmin extends AjaxModule {

    protected function impl() {
        // read post fields
        if(!isset($_POST["action"]) || !is_string($_POST["action"])) $this->errorBadRequest("Invalid Parameter");
        if(!isset($_POST["relId"]) || !is_numeric($_POST["relId"])) $this->errorBadRequest("Invalid Parameter");
        //if(!isset($_POST["author"]) || !is_string($_POST["author"])) $this->errorBadRequest("Invalid Parameter");
        
        $user = SessionUtils::getInstance()->getLogin()["name"] ?? "";
        $userlevel = Poggit::getAdminLevel($user);
        switch ($_POST["action"]) {
        
            case "add":
                $uid = OfficialReviewModule::getUIDFromName($user);
                MysqlUtils::query("INSERT INTO release_reviews (releaseId, user, criteria, type, cat, score, message) VALUES (?, ? ,? ,? ,? ,? ,?)",
                "iiiiiis", $_POST["relId"], $uid , $_POST["criteria"] ?? 0, $_POST["type"],$_POST["category"], $_POST["score"], $_POST["message"]);
                break;
            
            case "delete" :
                if (($userlevel >= Poggit::MODERATOR && $userlevel >= Poggit::getAdminLevel($_POST["author"])) || ($_POST["author"] === $user)) { // Moderators up
                 MysqlUtils::query("DELETE FROM release_reviews WHERE (releaseId = ? AND user = ? AND criteria = ?)",
                "iii", $_POST["relId"], $_POST["author"], $_POST["criteria"]);
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
