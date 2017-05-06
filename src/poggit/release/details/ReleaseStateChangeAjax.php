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

namespace poggit\release\details;

use poggit\account\SessionUtils;
use poggit\module\AjaxModule;
use poggit\Poggit;
use poggit\utils\internet\MysqlUtils;

class ReleaseStateChangeAjax extends AjaxModule {
    protected function impl() {
        // read post fields
        $relId = (int) $this->param("relId", $_POST);
        if(!is_numeric($relId)) $this->errorBadRequest("relId should be numeric");

        $user = SessionUtils::getInstance()->getLogin()["name"] ?? "";
        $state = $this->param("state");
        if(!is_numeric($state)) $this->errorBadRequest("state must be numeric");
        if(Poggit::getUserAccess($user) >= Poggit::MODERATOR) {
            MysqlUtils::query("UPDATE releases SET state = ? WHERE releaseId = ?", "ii", $state, $relId);
            Poggit::getLog()->w("$user set releaseId $relId to state $state");
            echo json_encode([
                "state" => $state
            ]);
        }
    }

    public function getName(): string {
        return "release.statechange";
    }
}
