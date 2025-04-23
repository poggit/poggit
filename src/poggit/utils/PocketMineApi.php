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

use poggit\utils\internet\Mysql;
use function apcu_exists;
use function apcu_fetch;
use function apcu_store;
use function count;

class PocketMineApi {
    const KEY_PROMOTED = "poggit.pmapis.promoted";
    const KEY_PROMOTED_COMPAT = "poggit.pmapis.promotedCompat";
    const KEY_LATEST = "poggit.pmapis.latest";
    const KEY_LATEST_COMPAT = "poggit.pmapis.latestCompat";
    const KEY_VERSIONS = "poggit.pmapis.versions";

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
        if(count(apcu_exists([self::KEY_PROMOTED, self::KEY_PROMOTED_COMPAT, self::KEY_LATEST, self::KEY_LATEST_COMPAT])) === 4) {
            self::$PROMOTED = apcu_fetch(self::KEY_PROMOTED);
            self::$PROMOTED_COMPAT = apcu_fetch(self::KEY_PROMOTED_COMPAT);
            self::$LATEST = apcu_fetch(self::KEY_LATEST);
            self::$LATEST_COMPAT = apcu_fetch(self::KEY_LATEST_COMPAT);
        } else {
            foreach(Mysql::query("SELECT name, value FROM spoon_prom WHERE name != ?", "s", self::KEY_VERSIONS) as $row) {
                switch($row["name"]) {
                    case self::KEY_PROMOTED:
                        self::$PROMOTED = $row["value"];
                        break;
                    case self::KEY_PROMOTED_COMPAT:
                        self::$PROMOTED_COMPAT = $row["value"];
                        break;
                    case self::KEY_LATEST:
                        self::$LATEST = $row["value"];
                        break;
                    case self::KEY_LATEST_COMPAT:
                        self::$LATEST_COMPAT = $row["value"];
                        break;
                    default:
                        continue 2;
                }
                apcu_store($row["name"], $row["value"], 86400);
            }
        }

        if(apcu_exists(self::KEY_VERSIONS)) {
            self::$VERSIONS = apcu_fetch(self::KEY_VERSIONS);
        } else {
            $versions = Mysql::query("SELECT id, name, incompatible FROM known_spoons");
            foreach($versions as $row) {
                self::$VERSIONS[$row["name"]] = [
                    "id" => (int) $row["id"],
                    "incompatible" => (bool) $row["incompatible"]
                ];
            }
            apcu_store(self::KEY_VERSIONS, self::$VERSIONS, 86400);
        }
    }
}

PocketMineApi::init();
