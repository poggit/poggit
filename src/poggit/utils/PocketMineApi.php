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

namespace poggit\utils;

use const poggit\ASSETS_PATH;
use function poggit\virion\pmApiVersions;

require_once ASSETS_PATH . "php/virion.php";

class PocketMineApi {
    /** @var string The latest non-development API version */
    const PROMOTED = "2.1.0";

    /**
     * @var string[][] Lists ALL known PocketMine API versions.
     *
     * Must be in ascending order of API level,
     * i.e. version_compare(array_keys($VERSIONS)[$n], array_keys($VERSIONS)[$n + 1], "<") must be true
     */
    public static $VERSIONS;
}

PocketMineApi::$VERSIONS = pmApiVersions();
