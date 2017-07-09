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

use poggit\module\AjaxModule;
use poggit\release\PluginRelease;
use poggit\utils\internet\Mysql;

class GetReleaseVersionsAjax extends AjaxModule {
    public function getName(): string {
        return "submit.deps.getversions";
    }

    protected function needLogin(): bool {
        return false;
    }

    protected function impl() {
        $versions = Mysql::query("SELECT releaseId, version, state, flags, UNIX_TIMESTAMP(creation) submitTime, UNIX_TIMESTAMP(updateTime) updateTime FROM releases
                WHERE name = ? AND state >= ? ORDER BY submitTime DESC", "si", $this->param("name"), PluginRelease::RELEASE_STATE_SUBMITTED);
        $output = [];
        foreach($versions as $version) {
            $output[(int) $version["releaseId"]] = [
                "version" => $version["version"],
                "state" => (int) $version["state"],
                "stateName" => PluginRelease::$STATE_ID_TO_HUMAN[(int) $version["state"]],
                "preRelease" => ((int) $version["flags"] & PluginRelease::RELEASE_FLAG_PRE_RELEASE) > 0,
                "official" => (PluginRelease::RELEASE_FLAG_OFFICIAL & (int) $version["flags"]) > 0,
                "outdated" => (PluginRelease::RELEASE_FLAG_OUTDATED & (int) $version["flags"]) > 0,
                "submitTime" => (float) $version["submitTime"],
                "updateTime" => (float) $version["updateTime"],
            ];
        }
        echo json_encode($output, JSON_FORCE_OBJECT);
    }
}
