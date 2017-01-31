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

use poggit\Poggit;

class ProxyLinkModule extends Module {
    static $TABLE = [
        "ghhst" => "https://help.github.com/articles/about-required-status-checks/",
        "orgperms" => "https://github.com/settings/connections/applications/27a6a18555e95fce1a74",
        "defavt" => "https://1.gravatar.com/avatar/0c95b990a17fa0aacf4f2da13e5729d9?d=https%3A%2F%2Fassets-cdn.github.com%2Fimages%2Fgravatars%2Fgravatar-user-420.png&r=x&s=140",
    ];

    public function getName(): string {
        return "rd";
    }

    public function getAllNames(): array {
        return array_keys(self::$TABLE);
    }

    public function output() {
        if(isset(self::$TABLE[$mn = strtolower(Poggit::getModuleName())])) {
            http_response_code(301);
            Poggit::redirect(self::$TABLE[$mn], true);
        } else {
            $this->errorNotFound(false);
        }
    }
}
