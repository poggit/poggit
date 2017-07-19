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

namespace poggit\utils\internet;

use mysqli;
use poggit\errdoc\InternalErrorPage;
use poggit\Meta;
use poggit\utils\OutputManager;
use RuntimeException;

class Mysql {
    public static function insertBulk(string $baseQuery, string $format, array $data, callable $mapper) {
        $query = $baseQuery . " ";
        $baseGroup = "(" . substr(str_repeat(",?", strlen($format)), 1) . ")";
        $query .= substr(str_repeat("," . $baseGroup, count($data)), 1);
        Mysql::query($query, str_repeat($format, count($data)), ...array_merge(...array_map($mapper, $data)));
    }

    public static function arrayQuery(string $format, array ...$inArgs) {
        $qm = [];
        $types = "";
        $outArgs = [];
        foreach($inArgs as list($type, $arg)) {
            Meta::getLog()->jd([$type, $arg]);
            if(is_array($arg)) {
                $qm[] = substr(str_repeat(",?", count($arg)), 1);
                $types .= str_repeat($type, count($arg));
                $outArgs = array_merge($outArgs, $arg);
            } else {
                $qm[] = "?";
                $types .= $type;
                $outArgs[] = $arg;
            }
        }
        return Mysql::query(vsprintf($format, $qm), $types, ...$outArgs);
    }

    public static function query(string $query, string $types = "", ...$args) {
        Curl::$mysqlCounter++;
        $start = microtime(true);
        $db = self::getDb();
        if($types !== "") {
            Meta::getLog()->v("Executing MySQL query $query with args $types: " . (json_encode($args) ?: base64_encode(var_export($args, true))));
            $stmt = $db->prepare($query);
            if($stmt === false) throw new RuntimeException("Failed to prepare statement: " . $db->error);
            $stmt->bind_param($types, ...$args);
            if(!$stmt->execute()) throw new RuntimeException("Failed to execute query: " . $db->error);
            $result = $stmt->get_result();
        } else {
            Meta::getLog()->v("Executing MySQL query $query");
            $result = $db->query($query);
            if($result === false) throw new RuntimeException("Failed to execute query: " . $db->error);
        }
        if($result instanceof \mysqli_result) {
            /** @var array[] $rows */
            $rows = [];
            while(is_array($row = $result->fetch_assoc())) {
                $rows[] = $row;
            }
            Meta::getLog()->v("Got result with " . count($rows) . " rows, took " . round((microtime(true) - $start) * 1000) . " ms");
            $ret = $rows;
        } else {
            $ret = $db;
        }
        $end = microtime(true);
        Curl::$mysqlTime += $end - $start;
        return $ret;
    }

    public static function getDb(): mysqli {
        global $db;
        if(isset($db)) return $db;

        $data = Meta::getSecret("mysql");
        try {
            /** @noinspection PhpUsageOfSilenceOperatorInspection */
            $db = @new mysqli($data["host"], $data["user"], $data["password"], $data["schema"], $data["port"] ?? 3306);
        } catch(\Exception $e) {
            Meta::getLog()->e("mysqli error: " . $e->getMessage());
        }
        if($db->connect_error) {
            $rand = mt_rand();
            Meta::getLog()->e("Error#$rand mysqli error: $db->connect_error");
            OutputManager::$current->terminate();
            (new InternalErrorPage($rand))->output();
            die;
        }
        return $db;
    }
}
