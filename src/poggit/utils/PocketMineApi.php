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

class PocketMineApi {
    /** @var string The latest non-development API version */
    const PROMOTED = "2.1.0";
    /** @var string The earliest version that servers running on the latest non-development API version can support */
    const PROMOTED_COMPAT = "2.0.0";
    /** @var string The latest API version */
    const LATEST = "3.0.0-ALPHA10";
    /** @var string The earliest version that servers running on the latest API can support */
    const LATEST_COMPAT = "3.0.0-ALPHA10";

    /**
     * @var string[][][]|bool[][] Lists ALL known PocketMine API versions.
     *                          Must be in ascending order of API level, i.e. version_compare(array_keys($VERSIONS)[$n],
     *                          array_keys($VERSIONS)[$n + 1], "<") must be true.
     *
     *                          "description" is an array of important changes in the API since the last one
     *
     *                          "php" is an array of the PHP minor (not patch) versions that users may use in this API
     *
     *                          "incompatible" is whether servers in this API version can load plugins in the previous
     *                          API version. This is usually the first version in each major version.
     */
    public static $VERSIONS = [
        "1.0.0" => ["description" => ["First API version after 2014 core-rewrite"], "php" => ["5.6"], "incompatible" => true, "indev" => false, "phar" => ["default" => null]],
        "1.1.0" => ["description" => [], "php" => ["5.6"], "incompatible" => false, "indev" => false, "phar" => ["default" => null]],
        "1.2.1" => ["description" => [], "php" => ["5.6"], "incompatible" => false, "indev" => false, "phar" => ["default" => null]],
        "1.3.0" => ["description" => [], "php" => ["5.6"], "incompatible" => false, "indev" => false, "phar" => ["default" => null]],
        "1.3.1" => ["description" => [], "php" => ["5.6"], "incompatible" => false, "indev" => false, "phar" => ["default" => null]],
        "1.4.0" => ["description" => [], "php" => ["5.6"], "incompatible" => false, "indev" => false, "phar" => ["default" => null]],
        "1.4.1" => ["description" => [], "php" => ["5.6"], "incompatible" => false, "indev" => false, "phar" => ["default" => null]],
        "1.5.0" => ["description" => [], "php" => ["5.6"], "incompatible" => false, "indev" => false, "phar" => ["default" => null]],
        "1.6.0" => ["description" => [], "php" => ["5.6"], "incompatible" => false, "indev" => false, "phar" => ["default" => null]],
        "1.6.1" => ["description" => [], "php" => ["5.6"], "incompatible" => false, "indev" => false, "phar" => ["default" => null]],
        "1.7.0" => ["description" => [], "php" => ["5.6"], "incompatible" => false, "indev" => false, "phar" => ["default" => null]],
        "1.7.1" => ["description" => [], "php" => ["5.6"], "incompatible" => false, "indev" => false, "phar" => ["default" => null]],
        "1.8.0" => ["description" => [], "php" => ["5.6"], "incompatible" => false, "indev" => false, "phar" => ["default" => null]],
        "1.9.0" => ["description" => [], "php" => ["5.6"], "incompatible" => false, "indev" => false, "phar" => ["default" => null]],
        "1.10.0" => ["description" => [], "php" => ["5.6"], "incompatible" => false, "indev" => false, "phar" => ["default" => null]],
        "1.11.0" => ["description" => [], "php" => ["5.6"], "incompatible" => false, "indev" => false, "phar" => ["default" => null]],
        "1.12.0" => ["description" => [], "php" => ["5.6"], "incompatible" => false, "indev" => false, "phar" => ["default" => null]],
        "1.13.0" => ["description" => [], "php" => ["5.6"], "incompatible" => false, "indev" => false, "phar" => ["default" => null]],
        "2.0.0" => ["description" => ["Starts supporting PHP 7"], "php" => ["7.0"], "incompatible" => true, "indev" => false, "phar" => ["default" => null]],
        "2.1.0" => ["description" => ["Metadata updates", "AsyncTask advanced features"], "php" => ["7.0"], "incompatible" => false, "indev" => false, "phar" => ["default" => null]],
        "3.0.0-ALPHA1" => ["description" => [], "php" => ["7.0"], "incompatible" => true, "indev" => false, "phar" => ["default" => null]],
        "3.0.0-ALPHA2" => ["description" => [], "php" => ["7.0"], "incompatible" => true, "indev" => false, "phar" => ["default" => null]],
        "3.0.0-ALPHA3" => ["description" => [], "php" => ["7.0"], "incompatible" => true, "indev" => false, "phar" => ["default" => null]],
        "3.0.0-ALPHA4" => ["description" => [], "php" => ["7.0"], "incompatible" => true, "indev" => false, "phar" => ["default" => null]],
        "3.0.0-ALPHA5" => ["description" => [], "php" => ["7.0"], "incompatible" => true, "indev" => false, "phar" => ["default" => null]],
        "3.0.0-ALPHA6" => ["description" => ["Strict types"], "php" => ["7.0"], "incompatible" => true, "indev" => false, "phar" => ["default" => null]],
        "3.0.0-ALPHA7" => ["description" => ["Type hints"], "php" => ["7.0"], "incompatible" => true, "indev" => false, "phar" => ["default" => "https://github.com/pmmp/PocketMine-MP/releases/download/1.7dev-27/PocketMine-MP_1.7dev-27_3b968967_API-3.0.0-ALPHA7.phar"]],
        "3.0.0-ALPHA8" => ["description" => ["AsyncTask constructor changes"], "php" => ["7.2"], "incompatible" => true, "indev" => false, "phar" => ["default" => "https://github.com/pmmp/PocketMine-MP/releases/download/api%2F3.0.0-ALPHA8/PocketMine-MP_1.7dev-83_6e5759b1_API-3.0.0-ALPHA8.phar"]],
        "3.0.0-ALPHA9" => ["description" => ["New skin API"], "php" => ["7.2"], "incompatible" => true, "indev" => false, "phar" => ["default" => "https://github.com/pmmp/PocketMine-MP/releases/download/api%2F3.0.0-ALPHA9/PocketMine-MP_1.7dev-318_716c1f29_API-3.0.0-ALPHA9.phar"]],
        "3.0.0-ALPHA10" => ["description" => ["Who Knows"], "php" => ["7.2"], "incompatible" => true, "indev" => true, "phar" => ["default" => "https://github.com/pmmp/PocketMine-MP/releases/download/1.7dev-516/PocketMine-MP_1.7dev-516_fbd04b0f_API-3.0.0-ALPHA10.phar"]],
    ];
}
