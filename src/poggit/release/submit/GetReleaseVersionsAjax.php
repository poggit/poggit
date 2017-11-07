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

namespace poggit\release\submit;

use poggit\account\Session;
use poggit\module\AjaxModule;
use poggit\release\Release;
use poggit\utils\internet\Mysql;
use poggit\utils\internet\Curl;

class GetReleaseVersionsAjax extends AjaxModule {
    public function getName(): string {
        return "submit.deps.getversions";
    }

    protected function needLogin(): bool {
        return true;
    }

    protected function impl() {
        $versions = Mysql::query("SELECT releaseId, version, state, flags, rp.owner as repoowner, rp.name as reponame, UNIX_TIMESTAMP(creation) submitTime, UNIX_TIMESTAMP(updateTime) updateTime FROM releases r
                INNER JOIN projects p ON r.projectId = p.projectId
                INNER JOIN repos rp ON p.repoId = rp.repoId
                WHERE r.name = ? AND r.state >= ? ORDER BY submitTime DESC", "si", $this->param("name"), Release::STATE_SUBMITTED);
        $output = [];
        $session = Session::getInstance();
        foreach($versions as $version) {
            if(!($this->param("owner") === 'true') || Curl::testPermission($version["repoowner"] . "/" . $version["reponame"], $session->getAccessToken(), $session->getName(), "push")) {
                $output[(int) $version["releaseId"]] = [
                    "version" => $version["version"],
                    "state" => (int) $version["state"],
                    "stateName" => Release::$STATE_ID_TO_HUMAN[(int) $version["state"]],
                    "preRelease" => ((int) $version["flags"] & Release::FLAG_PRE_RELEASE) > 0,
                    "official" => (Release::FLAG_OFFICIAL & (int) $version["flags"]) > 0,
                    "outdated" => (Release::FLAG_OUTDATED & (int) $version["flags"]) > 0,
                    "obsolete" => (Release::FLAG_OBSOLETE & (int) $version["flags"]) > 0,
                    "submitTime" => (float) $version["submitTime"],
                    "updateTime" => (float) $version["updateTime"],
                ];
            }
        }
        echo json_encode($output, JSON_FORCE_OBJECT);
    }
}
