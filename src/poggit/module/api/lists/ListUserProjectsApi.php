<?php

/*
 * Poggit
 *
 * Copyright (C) 2016 Poggit
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

namespace poggit\module\api\lists;

use poggit\module\api\ApiException;
use poggit\module\api\ApiHandler;
use poggit\module\api\ApiModule;
use poggit\module\api\response\ProjectBrief;
use poggit\module\api\response\RepoBrief;
use poggit\module\api\response\UserBrief;
use poggit\utils\internet\CurlUtils;
use poggit\utils\internet\MysqlUtils;

class ListUserProjectsApi extends ApiHandler {
    public function process(\stdClass $request) {
        if(ApiModule::$token === "") throw new ApiException("Login required");

        $url = isset($request->username) ? "users/$request->username/repos" : "user/repos";
        $repos = CurlUtils::ghApiGet("$url?per_page=100", ApiModule::$token);
        /** @var RepoBrief[] $output */
        $output = [];
        /** @var int[] $ids */
        $ids = [];
        foreach($repos as $repo) {
            $ub = new UserBrief();
            $ub->userId = $repo->owner->id;
            $ub->login = $repo->owner->login;
            $rb = new RepoBrief();
            $rb->owner = $ub;
            $rb->name = $repo->name;
            $rb->id = $repo->id;
            $rb->buildEnabled = false;
            $rb->projectCount = 0;
            $rb->projects = [];
            $ids[] = (int) $repo->id;
            $output[$rb->id] = $rb;
        }

        $implodeIds = implode(",", $ids);
        $projects = MysqlUtils::query("SELECT r.repoId, r.build, p.projectId, p.name, p.path, p.type, p.framework, p.lang
            FROM projects p INNER JOIN repos r ON p.repoId = r.repoId WHERE r.repoId IN ($implodeIds)");
        $buildCounts = [];
        foreach(MysqlUtils::query("SELECT p.name, b.class, COUNT(*) AS cnt FROM builds b 
            INNER JOIN projects p ON p.projectId = b.projectId
            WHERE p.repoId IN ($implodeIds) AND b.class IS NOT NULL GROUP BY p.name, b.class") as $row) {
            $row = (object) $row;
            $buildCounts[$row->name][(int) $row->class] = (int) $row->cnt;
        }

        foreach($projects as $row) {
            $row = (object) $row;
            $pb = new ProjectBrief();
            $pb->projectId = (int) $row->projectId;
            $pb->name = $row->name;
            $pb->path = $row->path;
            $pb->type = (int) $row->type;
            $pb->framework = $row->framework;
            $pb->langEnabled = (bool) (int) $row->lang;
            $pb->buildsCount = $buildCounts[$pb->name];

            $rb = $output[(int) $row->repoId];
            $rb->buildEnabled = (bool) (int) $row->build;
            $rb->projectCount++;
            $rb->projects[] = $pb;
        }
        return array_values($output);
    }
}
