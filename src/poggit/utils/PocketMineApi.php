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

namespace poggit\utils;

use function file_get_contents;
use const poggit\ASSETS_PATH;

class PocketMineApi {
    /** @var string The latest non-development API version */
    public static $PROMOTED;
    /** @var string The earliest version that servers running on the latest non-development API version can support */
    public static $PROMOTED_COMPAT;
    /** @var string The latest API version */
    public static $LATEST;
    /** @var string The earliest version that servers running on the latest API can support */
    public static $LATEST_COMPAT;

    /** @var string[][][]|bool[][] */
    public static $VERSIONS;

    public static function init() {
        $data = yaml_parse(file_get_contents(ASSETS_PATH . "pmapis.yml"));
        self::$PROMOTED = $data["promoted"];
        self::$PROMOTED_COMPAT = $data["promotedCompat"];
        self::$LATEST = $data["latest"];
        self::$LATEST_COMPAT = $data["latestCompat"];
        self::$VERSIONS = $data["versions"];
    }
}

PocketMineApi::init();
