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

class PocketMineApi {
    /** @var string The latest non-development API version */
    const PROMOTED = "2.1.0";

    /**
     * @var string[][] Lists ALL known PocketMine API versions.
     *
     * Must be in ascending order of API level,
     * i.e. version_compare(array_keys($VERSIONS)[$n], array_keys($VERSIONS)[$n + 1], "<") must be true
     */
    public static $VERSIONS = [
        "1.0.0" => ["First API version after 2014 core-rewrite"],
        "1.1.0" => [],
        "1.2.1" => [],
        "1.3.0" => [],
        "1.3.1" => [],
        "1.4.0" => [],
        "1.4.1" => [],
        "1.5.0" => [],
        "1.6.0" => [],
        "1.6.1" => [],
        "1.7.0" => [],
        "1.7.1" => [],
        "1.8.0" => [],
        "1.9.0" => [],
        "1.10.0" => [],
        "1.11.0" => [],
        "1.12.0" => [],
        "1.13.0" => [],
        "2.0.0" => ["Starts supporting PHP 7"],
        "2.1.0" => ["Metadata updates", "AsyncTask advanced features"],
        "3.0.0-ALPHA1" => ["UNSTABLE: use at your own risk"],
        "3.0.0-ALPHA2" => ["UNSTABLE: use at your own risk"],
        "3.0.0-ALPHA3" => ["UNSTABLE: use at your own risk"],
        "3.0.0-ALPHA4" => ["UNSTABLE: use at your own risk"],
        "3.0.0-ALPHA5" => ["UNSTABLE: use at your own risk"],
    ];
}
