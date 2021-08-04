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

declare(strict_types=1);

namespace poggit\account;

use poggit\Meta;
use poggit\module\AjaxModule;
use poggit\utils\internet\Mysql;
use RuntimeException;

class KeepOnlineAjax extends AjaxModule {
    protected function impl() {
        $session = Session::getInstance();
        if($session->isLoggedIn()) {
            try {
                Mysql::query("INSERT INTO user_ips (uid, ip) VALUES (?, ?) ON DUPLICATE KEY UPDATE time = CURRENT_TIMESTAMP", "is", $session->getUid(), Meta::getClientIP());
            } catch(RuntimeException $e) {}
        }

        $keepOnline = Mysql::query(/** @lang MySQL */
            "SELECT KeepOnline(?, ?) onlineCount", "si",
            Meta::getClientIP(), $session->getUid());
        if(is_array($keepOnline)) {
            echo $keepOnline[0]["onlineCount"];
        } else {
            echo 1;
        }
    }

    protected function needLogin(): bool {
        return false;
    }
}
