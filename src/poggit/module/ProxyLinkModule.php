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

namespace poggit\module;

use poggit\Meta;
use function array_keys;
use function http_response_code;
use function strtolower;

class ProxyLinkModule extends Module {
    const TABLE = [
        "ghhst" => "https://help.github.com/articles/about-required-status-checks/",
        "orgperms" => "https://github.com/settings/connections/applications/27a6a18555e95fce1a74",
        "defavt" => "https://assets-cdn.github.com/images/gravatars/gravatar-user-420.png",
        "std" => "https://github.com/poggit/support/blob/master/pqrs.md",
        "pqrs" => "https://github.com/poggit/support/blob/master/pqrs.md",
        "virion" => "https://github.com/poggit/support/blob/master/virion.md",
        "help.api" => "https://github.com/poggit/support/blob/master/api.md",
        "gh.topics" => "https://github.com/blog/2309-introducing-topics",
        "gh.pmmp" => "https://github.com/pmmp/PocketMine-MP",
        "faq" => "https://poggit.github.io/support/faq",
        "submit.rules" => "https://poggit.pmmp.io/rules.edit",
    ];

    public static function getNames(): array {
        return array_keys(self::TABLE);
    }

    public function output() {
        if(isset(self::TABLE[$mn = strtolower(Meta::getModuleName())])) {
            http_response_code(301);
            Meta::redirect(self::TABLE[$mn], true);
        } else {
            $this->errorNotFound();
        }
    }
}
