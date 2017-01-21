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

class ReviewAdmin extends AjaxModule {

    protected function impl() {
        // read post fields
        if(!isset($_POST["action"]) || !is_string($_POST["action"])) $this->errorBadRequest("Invalid Parameter");
        if(!isset($_POST["relId"]) || !is_numeric($_POST["relId"])) $this->errorBadRequest("Invalid Parameter");
        //if(!isset($_POST["author"]) || !is_string($_POST["author"])) $this->errorBadRequest("Invalid Parameter");
        
        $user = SessionUtils::getInstance()->getLogin()["name"] ?? "";
        $userlevel = Poggit::getAdminLevel($user);
        Poggit::getLog()->d($_POST["action"]);
        switch ($_POST["action"]) {
        
            case "add": 
                Poggit::getLog()->i("Add Review");
                break;
            
            case "delete" :
                Poggit::getLog()->i("Delete Review");
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
