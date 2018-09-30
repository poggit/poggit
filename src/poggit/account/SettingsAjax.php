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

use poggit\module\AjaxModule;
use poggit\utils\internet\Mysql;
use function constant;
use function json_encode;

class SettingsAjax extends AjaxModule {
    protected function impl() {
        $name = $this->param("name");
        $value = $this->param("value");
        if($value !== "true" and $value !== "false") {
            $this->errorBadRequest("Bad 'value'");
            return;
        }
        $bool = constant($value);
        $session = Session::getInstance();
        if(isset(SettingsModule::getOptions()[$name])) {
            $session->getLogin()["opts"]->{$name} = $bool;
            Mysql::query("UPDATE users SET opts=? WHERE uid=?", "si", json_encode($session->getLogin()["opts"]), $session->getUid());
            echo '{"status":true}';
        } else {
            $this->errorBadRequest("Unknown name $name");
        }
    }
}
