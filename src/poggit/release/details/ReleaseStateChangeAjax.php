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
use poggit\timeline\NewPluginUpdateTimeLineEvent;
use poggit\utils\internet\MysqlUtils;

class ReleaseStateChangeAjax extends AjaxModule {
    protected function impl() {
        // read post fields
        $relId = (int) $this->param("relId", $_POST);
        if(!is_numeric($relId)) $this->errorBadRequest("relId should be numeric");

        $user = SessionUtils::getInstance()->getName();
        $state = $this->param("state");
        if(!is_numeric($state)) $this->errorBadRequest("state must be numeric");
        if(Poggit::getUserAccess($user) >= Poggit::MODERATOR) {
            $currState = MysqlUtils::query("SELECT state FROM releases WHERE releaseId = ?", "i", $relId)[0]["state"];
            MysqlUtils::query("UPDATE releases SET state = ?, updateTime = CURRENT_TIMESTAMP WHERE releaseId = ?", "ii", $state, $relId);
            $event = new NewPluginUpdateTimeLineEvent();
            $event->releaseId = $relId;
            $event->oldState = $currState;
            $event->newState = $state;
            $event->changedBy = $user;
            $event->dispatch();
            Poggit::getLog()->w("$user changed releaseId $relId from state $currState to $state");
            echo json_encode([
                "state" => $state
            ]);
        }
    }

    public function getName(): string {
        return "release.statechange";
    }
}
