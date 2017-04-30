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

namespace poggit\ci\api;

use poggit\account\SessionUtils;
use poggit\module\AjaxModule;
use poggit\utils\internet\CurlUtils;
use poggit\utils\internet\MysqlUtils;

class TriggerFirstPushAjax extends AjaxModule {
    protected function impl() {
        $owner = $_REQUEST["owner"] or $this->errorBadRequest("Missing 'owner'");
        $name = $_REQUEST["name"] or $this->errorBadRequest("Missing 'name'");

        $rows = MysqlUtils::query("SELECT IF(build, 1, 0) build, webhookId FROM repos WHERE owner = ? AND name = ?", "ss", $owner, $name);
        if(count($rows) === 0) $this->errorBadRequest("Nonexistent repository, or build not enabled");
        $row = $rows[0];
        if(!((int) $row["build"])) $this->errorBadRequest("Build not enabled for repo");
        $webhookId = (int) $row["webhookId"];

        CurlUtils::ghApiPost("repos/$owner/$name/hooks/$webhookId/tests", [], SessionUtils::getInstance()->getAccessToken(), true); // returns 204 No Content, so nonJson

        echo json_encode([
            "httpCode" => CurlUtils::$lastCurlResponseCode,
            "headers" => CurlUtils::$lastCurlHeaders
        ]);
    }

    public function getName(): string {
        return "ci.webhookTest";
    }
}
