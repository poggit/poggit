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

class ReleaseAdmin extends AjaxModule {

    protected function impl() {
        // read post fields
        if(!isset($_POST["relId"]) || !is_numeric($_POST["relId"])) $this->errorBadRequest("Invalid Parameter");
        if(!isset($_POST["state"]) || !is_numeric($_POST["state"])) $this->errorBadRequest("Invalid Parameter");

        $user = SessionUtils::getInstance()->getLogin()["name"] ?? "";
        if (Poggit::getAdminLevel($user) === Poggit::ADMIN) {
            
        $relId = $_POST["relId"];
        $state = $_POST["state"];
        MysqlUtils::query("UPDATE releases SET state = ? WHERE releaseId = ?",
            "ii", $state, $relId);       
            Poggit::getLog()->i("$user set releaseId $relId to stage $state");
        }
        echo json_encode([
            "state" => $state
        ]);
    }

    public function getName(): string {
        return "release.admin";
    }

    protected function needLogin(): bool {
        return true;
    }
}
