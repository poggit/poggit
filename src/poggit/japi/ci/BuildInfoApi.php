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

namespace poggit\japi\ci;

use poggit\japi\ApiException;
use poggit\japi\ApiHandler;
use poggit\japi\ApiModule;
use poggit\utils\internet\CurlUtils;
use poggit\utils\internet\GitHubAPIException;
use poggit\utils\internet\MysqlUtils;

class BuildInfoApi extends ApiHandler {
    public function process(\stdClass $request) {
        $buildId = (int) $request->buildId;
        $rows = MysqlUtils::query("SELECT 
            r.repoId, r.owner AS rowner, r.name AS rname, p.name AS pname, b.class, b.internal, b.created
            FROM builds b INNER JOIN projects p ON p.projectId = b.projectId INNER JOIN repos r ON r.repoId = p.repoId
            WHERE b.buildId = ?", "i", $buildId);
        if(count($rows) === 0) throw new ApiException("Build not found");
        $row = (object) $rows[0];
        try {
            CurlUtils::ghApiGet("repositories/$row->repoId", ApiModule::$token);
        } catch(GitHubAPIException $e) {
            throw new ApiException("You have no access to this private repo");
        }
        return $row;
    }
}
