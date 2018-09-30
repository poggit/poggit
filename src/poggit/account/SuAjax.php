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

namespace poggit\account;

use poggit\Meta;
use poggit\module\AjaxModule;
use poggit\utils\internet\Mysql;
use function explode;
use function http_response_code;
use function json_decode;

class SuAjax extends AjaxModule {
    protected function impl() {
        if(Meta::getAdmlv() !== Meta::ADMLV_ADMIN) {
            http_response_code(403);
            echo '{"error":"forbidden"}';
            exit;
        }
        $target = $_REQUEST["target"];
        $row = Mysql::query("SELECT uid, name, token, scopes, UNIX_TIMESTAMP(lastLogin) lastLogin, UNIX_TIMESTAMP(lastNotif) lastNotif, opts
                FROM users WHERE name = ?", "s", $target)[0] ?? null;
        if($row === null) {
            http_response_code(404);
            echo '{"error":"no such user"}';
            return;
        }
        $row = (object) $row;
        $opts = json_decode($row->opts);
        if(!($opts->allowSu ?? false)) {
            http_response_code(401);
            echo '{"error":"su not authorized"}';
            return;
        }

        $opts->su = true;
        Session::getInstance()->login($row->uid, $row->name, $row->token, explode(",", $row->scopes), $row->lastLogin, $row->lastNotif, $opts);
        echo '{"status":true}';
    }
}
