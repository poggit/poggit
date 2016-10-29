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

namespace poggit\module;

use function poggit\redirect;

class ProxyLinkModule extends Module {
    static $TABLE = [
        "ghhst" => "https://help.github.com/articles/about-required-status-checks/",
    ];

    public function getName() : string {
        return "rd";
    }

    public function getAllNames() : array {
        return ["rd", "ghhst"];
    }

    public function output() {
        if(isset(self::$TABLE[$GLOBALS["moduleName"]])) {
            http_response_code(301);
            redirect(self::$TABLE[strtolower($GLOBALS["moduleName"])], true);
        }else{
            $this->errorNotFound(false);
        }
    }
}
