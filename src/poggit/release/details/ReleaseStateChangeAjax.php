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

use poggit\account\Session;
use poggit\Meta;
use poggit\module\AjaxModule;
use poggit\release\Release;
use poggit\timeline\NewPluginUpdateTimeLineEvent;
use poggit\utils\internet\Mysql;

class ReleaseStateChangeAjax extends AjaxModule {
    protected function impl() {
        // read post fields
        $releaseId = (int) $this->param("relId", $_POST);
        if(!is_numeric($releaseId)) $this->errorBadRequest("relId should be numeric");

        $user = Session::getInstance()->getName();
        $newState = $this->param("state");
        if(!is_numeric($newState)) $this->errorBadRequest("state must be numeric");
        $newState = (int) $newState;
        if(Meta::getAdmlv($user) < Meta::ADMLV_MODERATOR) $this->errorAccessDenied();

        $info = Mysql::query("SELECT state, projectId FROM releases WHERE releaseId = ?", "i", $releaseId);
        if(!isset($info[0])) $this->errorNotFound(true);
        $oldState = (int) $info[0]["state"];
        $projectId = (int) $info[0]["projectId"];
        Mysql::query("UPDATE releases SET state = ?, updateTime = CURRENT_TIMESTAMP WHERE releaseId = ?", "ii", $newState, $releaseId);

        $maxRelId = (int) Mysql::query("SELECT IFNULL(MAX(releaseId), -1) id FROM releases WHERE projectId = ? AND state >= ?", "ii", $projectId, Release::STATE_VOTED)[0]["id"];
        $obsoleteFlag = Release::FLAG_OBSOLETE;
        if($maxRelId !== -1){
            Mysql::query("UPDATE releases SET flags = CASE
                WHEN releaseId >= ? THEN flags & (~?)
                ELSE flags | ?
            END WHERE projectId = ?", "iiii", $maxRelId, $obsoleteFlag, $obsoleteFlag, $projectId);
        }

        $event = new NewPluginUpdateTimeLineEvent();
        $event->releaseId = $releaseId;
        $event->oldState = $oldState;
        $event->newState = $newState;
        $event->changedBy = $user;
        $event->dispatch();
        Meta::getLog()->w("$user changed releaseId $releaseId from state $oldState to $newState. Head version is now release($maxRelId)");
        echo json_encode([
            "state" => $newState,
        ]);
    }

    public function getName(): string {
        return "release.statechange";
    }
}
