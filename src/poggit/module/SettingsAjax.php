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

namespace poggit\module;

use poggit\module\ajax\AjaxModule;
use poggit\utils\SessionUtils;

class SettingsAjax extends AjaxModule {

    protected function impl() {
        $name = $_REQUEST["name"] or $this->errorBadRequest("Missing 'name'");
        $value = $_REQUEST["value"] or $this->errorBadRequest("Missing 'value'");
        if(!defined($value) or !is_bool($c = constant($value))) {
            $this->errorBadRequest("Bad 'value'");
            return;
        }
        if($name === "allowSu") {
            SessionUtils::getInstance()->getLogin()["opts"]->allowSu = $c;
            echo '{"status":true}';
        } else {
            $this->errorBadRequest("Unknown name $name");
        }
    }

    public function getName(): string {
        return "opt.toggle";
    }
}
