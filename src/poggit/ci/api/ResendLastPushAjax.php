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

namespace poggit\ci\api;

use poggit\account\Session;
use poggit\module\AjaxModule;
use poggit\utils\internet\Curl;
use poggit\utils\internet\GitHub;
use poggit\utils\internet\Mysql;
use function count;
use function json_encode;

class ResendLastPushAjax extends AjaxModule {
    protected function impl() {
        $owner = $this->param("owner");
        $name = $this->param("name");

        $rows = Mysql::query("SELECT IF(build, 1, 0) build, webhookId FROM repos WHERE owner = ? AND name = ?", "ss", $owner, $name);
        if(count($rows) === 0) $this->errorBadRequest("Nonexistent repository, or build not enabled");
        $row = $rows[0];
        if(!((int) $row["build"])) $this->errorBadRequest("Build not enabled for repo");
        $webhookId = (int) $row["webhookId"];

        GitHub::ghApiPost("repos/$owner/$name/hooks/$webhookId/tests", [], Session::getInstance()->getAccessToken(), true); // returns 204 No Content, so nonJson

        echo json_encode([
            "httpCode" => Curl::$lastCurlResponseCode,
            "headers" => Curl::$lastCurlHeaders
        ]);
    }
}
