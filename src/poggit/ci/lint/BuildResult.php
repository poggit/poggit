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

namespace poggit\ci\lint;

use poggit\utils\internet\Mysql;
use ReflectionClass;
use function count;
use function json_decode;
use function json_encode;
use function str_repeat;
use function substr;

class BuildResult {
    const LEVEL_OK = 0;
    const LEVEL_LINT = 1;
    const LEVEL_WARN = 2;
    const LEVEL_ERROR = 3;
    const LEVEL_BUILD_ERROR = 4;

    public static $names = [
        self::LEVEL_OK => "OK",
        self::LEVEL_LINT => "Lint",
        self::LEVEL_WARN => "Warning",
        self::LEVEL_ERROR => "Error",
        self::LEVEL_BUILD_ERROR => "Build Error",
    ];

    public static $states = [
        self::LEVEL_OK => "success",
        self::LEVEL_LINT => "success",
        self::LEVEL_WARN => "failure",
        self::LEVEL_ERROR => "failure",
        self::LEVEL_BUILD_ERROR => "error",
    ];

    /** @var int */
    public $worstLevel = BuildResult::LEVEL_OK;

    /** @var V2BuildStatus[] */
    public $statuses = [];

    /** @var string[] */
    public $knownClasses = [];

    public $main;

    public function addStatus(V2BuildStatus $status) {
        $this->statuses[] = $status;
        if($this->worstLevel < $status->level) $this->worstLevel = $status->level;
    }

    public function storeMysql(int $buildId) {
        if(count($this->statuses) === 0) return;
        $query = "INSERT INTO builds_statuses (buildId, level, class, body) VALUES " .
            substr(str_repeat("(?,?,?,?),", count($this->statuses)), 0, -1);
        $params = [];
        foreach($this->statuses as $status) {
            $params[] = $buildId;
            $params[] = $status->level;
            $params[] = (new ReflectionClass($status))->getShortName();
            $params[] = json_encode($status);
        }
        Mysql::query($query, str_repeat("iiss", count($this->statuses)), ...$params);
    }

    /**
     * @param int[] $buildIds
     * @return BuildResult[] int buildId => BuildResult buildResult
     */
    public static function fetchMysqlBulk(array $buildIds): array {
        $query = "SELECT buildId, level, class, body FROM builds_statuses WHERE buildId IN (" .
            substr(str_repeat(",?", count($buildIds)), 1) . ")";
        $statuses = Mysql::query($query, str_repeat("i", count($buildIds)), ...$buildIds);
        /** @var BuildResult[] $results */
        $results = [];
        foreach($buildIds as $buildId) {
            $results[$buildId] = new self();
        }
        foreach($statuses as $row) {
            $status = V2BuildStatus::unserializeNew(json_decode($row["body"]), $row["class"], (int) $row["level"]);
            $results[(int) $row["buildId"]]->addStatus($status);
        }
        return $results;
    }

    public static function fetchMysql(int $buildId): BuildResult {
        $instance = new self();

        $statuses = Mysql::query("SELECT level, class, body FROM builds_statuses WHERE buildId = ?", "i", $buildId);
        foreach($statuses as $row) {
            $status = V2BuildStatus::unserializeNew(json_decode($row["body"]), $row["class"], (int) $row["level"]);
            $instance->addStatus($status);
        }

        return $instance;
    }
}
