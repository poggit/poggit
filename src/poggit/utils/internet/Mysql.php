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

namespace poggit\utils\internet;

use mysqli;
use poggit\errdoc\InternalErrorPage;
use poggit\Meta;
use poggit\utils\OutputManager;
use RuntimeException;

class Mysql {
    public static $mysqlTime = 0;
    public static $mysqlCounter = 0;

    public static function insertBulk(string $tblName, array $columns, array $data, callable $mapper) {
        if(count($data) === 0) return;
        $query = "INSERT INTO `$tblName` (" . implode(",", array_keys($columns)) . ") VALUES ";
        $rowTypes = implode(array_values($columns));
        $baseGroup = "(" . substr(str_repeat(",?", strlen($rowTypes)), 1) . ")";
        $query .= substr(str_repeat("," . $baseGroup, count($data)), 1);
        $args = [];
        foreach($data as $datum) {
            $row = $mapper($datum);
            assert(count($row) === strlen($rowTypes));
            foreach($row as $cell) {
                $args[] = $cell;
            }
        }
        self::query($query, str_repeat($rowTypes, count($data)), ...$args);
    }

    /**
     * Warning: $format must only use "%s" for args, not "?"!
     *
     * @param string  $format
     * @param array[] ...$inArgs
     * @return array[]|mysqli
     */
    public static function arrayQuery(string $format, array ...$inArgs) {
        $qm = [];
        $types = "";
        $outArgs = [];
        foreach($inArgs as list($type, $arg)) {
            if(is_array($arg)) {
                $qm[] = substr(str_repeat(",?", count($arg)), 1);
                $types .= str_repeat($type, count($arg));
                foreach($arg as $item){
                    $outArgs[] = $item;
                }
            } else {
                $qm[] = "?";
                $types .= $type;
                $outArgs[] = $arg;
            }
        }
        return self::query(vsprintf($format, $qm), $types, ...$outArgs);
    }

    public static function updateRow(string $table, array $columns, string $condition, string $conditionTypes, ...$conditionArgs) {
        if($columns === []) return;
        $query = "UPDATE `$table` SET ";
        $types = "";
        $args = [];
        foreach($columns as $column => list($type, $value)) {
            $query .= "$column = ?, ";
            $types .= $type;
            $args[] = $value;
        }
        $query = substr($query, 0, -2) . " WHERE " . $condition;
        $types .= $conditionTypes;
        $args = array_merge($args, $conditionArgs);
        self::query($query, $types, ...$args);
    }

    public static function query(string $query, string $types = "", ...$args) {
        self::$mysqlCounter++;
        $start = microtime(true);
        $db = self::getDb();
        if($types !== "") {
            if(Meta::isDebug()) Meta::getLog()->v("Executing MySQL query $query with args $types: " . (json_encode($args) ?: base64_encode(var_export($args, true))));
            $stmt = $db->prepare($query);
            if($stmt === false) throw new RuntimeException("Failed to prepare statement: " . $db->error);
            $stmt->bind_param($types, ...$args);
            if(!$stmt->execute()) throw new RuntimeException("Failed to execute query:\n" . $db->error . "\n" . $stmt->error . "\nArgs $types: " . json_encode($args));
            $result = $stmt->get_result();
            if($db->error) throw new RuntimeException("Failed to execute query: " . $db->error );
        } else {
            if(Meta::isDebug()) Meta::getLog()->v("Executing MySQL query $query");
            $result = $db->query($query);
            if($db->error) throw new RuntimeException("Failed to execute query: " . $db->error);
        }
        if($result instanceof \mysqli_result) {
            /** @var array[] $rows */
            $rows = [];
            while(is_array($row = $result->fetch_assoc())) {
                $rows[] = $row;
            }
            if(Meta::isDebug()) Meta::getLog()->v("Got result with " . count($rows) . " rows, took " . round((microtime(true) - $start) * 1000) . " ms");
            $ret = $rows;
        } else {
            $ret = $db;
        }
        $end = microtime(true);
        self::$mysqlTime += $end - $start;
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
            OutputManager::$tail->terminate();
            (new InternalErrorPage($rand))->output();
            die;
        }
        return $db;
    }
}
