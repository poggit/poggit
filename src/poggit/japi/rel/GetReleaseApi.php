<?php

/*
 * Poggit
 *
 * Copyright (C) 2016-2018 Poggit
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

namespace poggit\japi\rel;

use poggit\japi\ApiHandler;
use poggit\japi\response\ReleaseBrief;
use poggit\release\Release;
use poggit\utils\internet\Mysql;
use stdClass;
use function count;

class GetReleaseApi extends ApiHandler {

    public function process(stdClass $request) {
        $name = $request->name;
        $result = [];
        $matches = Mysql::query("SELECT
            r.releaseId, r.projectId AS projectId, r.name, r.version
            FROM releases r
            WHERE r.state >= ? AND r.name LIKE '%$name%' LIMIT 10", 'i', Release::STATE_CHECKED);
        if(count($matches) > 0) {
            foreach($matches as $match) {
                if((int) $match["releaseId"] === (int) $request->releaseId) continue;
                $brief = new ReleaseBrief();
                $brief->projectId = (int) $match["projectId"];
                $brief->name = $match["name"];
                $brief->releaseId = $match["releaseId"];
                $brief->version = $match["version"];
                $result[] = $brief;
            }
        }
        return $result;
    }
}
