<?php

/*
 * Copyright 2016 poggit
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

namespace poggit;

use mysqli;
use poggit\exception\GitHubAPIException;
use poggit\log\Log;
use poggit\output\OutputManager;
use poggit\page\error\InternalErrorPage;
use RuntimeException;

class Poggit {
    public static $curlCounter = 0;
    public static $curlTime = 0;
    public static $mysqlCounter = 0;
    public static $mysqlTime = 0;

    public static function getRootPath() : string {
        return "/" . trim(Poggit::getSecret("paths.url"), "/") . "/";
    }

    public static function getSecret(string $name) {
        global $secretsCache;
        if(!isset($secretsCache)) {
            $secretsCache = json_decode(file_get_contents(SECRET_PATH . "secrets.json"), true);
        }
        $secrets = $secretsCache;
        if(isset($secrets[$name])) {
            return $secrets[$name];
        }
        $parts = explode(".", $name);
        foreach($parts as $part) {
            if(!is_array($secrets) or !isset($secrets[$part])) {
                throw new RuntimeException("Unknown secret $part");
            }
            $secrets = $secrets[$part];
        }
        if(count($parts) > 1) {
            $secretsCache[$name] = $secrets;
        }
        return $secrets;
    }

    public static function getDb() : mysqli {
        global $db;
        if(isset($db)) {
            return $db;
        }
        $data = Poggit::getSecret("mysql");
        try {
            /** @noinspection PhpUsageOfSilenceOperatorInspection */
            $db = @new mysqli($data["host"], $data["user"], $data["password"], $data["schema"], $data["port"] ?? 3306);
        } catch(\Exception $e) {
            Poggit::getLog()->e("mysqli error: " . $e->getMessage());
        }
        if($db->connect_error) {
            $rand = mt_rand();
            Poggit::getLog()->e("Error#$rand mysqli error: $db->connect_error");
            OutputManager::$current->terminate();
            (new InternalErrorPage($rand))->output();
            die;
        }
        return $db;
    }

    public static function queryAndFetch(string $query, string $types = "", ...$args) {
        self::$mysqlCounter++;
        $start = microtime(true);
        $db = self::getDb();
        if($types !== "") {
            $stmt = $db->prepare($query);
            if($stmt === false) {
                throw new RuntimeException("Failed to prepare statement: " . $db->error);
            }
//            $args = [];
//            foreach($params as $k => &$param){
//                $args[$k] =& $param;
//            }
            self::getLog()->d($query);
            self::getLog()->d(count($args));
            $stmt->bind_param($types, ...$args);
            if(!$stmt->execute()) {
                throw new RuntimeException("Failed to execute query: " . $db->error);
            }
            $result = $stmt->get_result();
        } else {
            $result = $db->query($query);
            if($result === false) {
                throw new RuntimeException("Failed to execute query: " . $db->error);
            }
        }
        if($result instanceof \mysqli_result) {
            $rows = [];
            while(is_array($row = $result->fetch_assoc())) {
                $rows[] = $row;
            }
            $ret = $rows;
        } else {
            $ret = $db;
        }
        $end = microtime(true);
        self::$mysqlTime += $end - $start;
        return $ret;
    }

    public static function curl(string $url, string $postContents, string $method, string ...$extraHeaders) {
        self::$curlCounter++;
        $headers = ["User-Agent: Poggit/1.0", "Accept: application/json"];
        foreach($extraHeaders as $header) {
            if(strpos($header, "Accept: ") === 0) {
                $headers[1] = $header;
            } else {
                $headers[] = $header;
            }
        }
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postContents);
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $startTime = microtime(true);
        $ret = curl_exec($ch);
        $endTime = microtime(true);
        self::$curlTime += $endTime - $startTime;
        curl_close($ch);
        return $ret;
    }

    public static function curlPost(string $url, $postFields, string ...$extraHeaders) {
        self::$curlCounter++;
        $headers = ["User-Agent: Poggit/1.0", "Accept: application/json"];
        foreach($extraHeaders as $header) {
            if(strpos($header, "Accept: ") === 0) {
                $headers[1] = $header;
            } else {
                $headers[] = $header;
            }
        }
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $startTime = microtime(true);
        $ret = curl_exec($ch);
        $endTime = microtime(true);
        self::$curlTime += $endTime - $startTime;
        curl_close($ch);
        return $ret;
    }

    public static function curlGet(string $url, string ...$extraHeaders) {
        self::$curlCounter++;
        $headers = ["User-Agent: Poggit/1.0", "Accept: application/json"];
        foreach($extraHeaders as $header) {
            if(strpos($header, "Accept: ") === 0) {
                $headers[1] = $header;
            } else {
                $headers[] = $header;
            }
        }
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $startTime = microtime(true);
        $ret = curl_exec($ch);
        $endTime = microtime(true);
        self::$curlTime += $endTime - $startTime;
        curl_close($ch);
        return $ret;
    }

    public static function ghApiCustom(string $url, string $customMethod, $postFields, string $token = "", bool $customAccept = false) {
        $headers = [];
        if($customAccept) {
            $headers[] = EARLY_ACCEPT;
        }
        if($token) {
            $headers[] = "Authorization: bearer $token";
        }
        $data = Poggit::curl($url, $postFields, $customMethod, ...$headers);
        if(is_string($data)) {
            $data = json_decode($data);
            if(is_object($data)) {
                if(!isset($data->message, $data->documentation_url)) {
                    return $data;
                }
                throw new GitHubAPIException($data->message);
            } elseif(is_array($data)) {
                return $data;
            }
        }
        throw new RuntimeException("Failed to access data from GitHub API: " . json_encode($data));
    }
    
    public static function ghApiPost(string $url, $postFields, string $token = "", bool $customAccept = false) {
        $headers = [];
        if($customAccept) {
            $headers[] = EARLY_ACCEPT;
        }
        if($token) {
            $headers[] = "Authorization: bearer $token";
        }
        $data = Poggit::curlPost($url, $postFields, ...$headers);
        if(is_string($data)) {
            $data = json_decode($data);
            if(is_object($data)) {
                if(!isset($data->message, $data->documentation_url)) {
                    return $data;
                }
                throw new GitHubAPIException($data->message);
            } elseif(is_array($data)) {
                return $data;
            }
        }
        throw new RuntimeException("Failed to access data from GitHub API: " . json_encode($data));
    }

    public static function ghApiGet(string $url, string $token = "", bool $customAccept = false) {
        $headers = [];
        if($customAccept) {
            $headers[] = EARLY_ACCEPT;
        }
        if($token) {
            $headers[] = "Authorization: bearer $token";
        }
        $data = Poggit::curlGet($url, ...$headers);
        if(is_string($data)) {
            $data = json_decode($data);
            if(is_object($data)) {
                if(!isset($data->message, $data->documentation_url)) {
                    return $data;
                }
                throw new GitHubAPIException($data->message);
            } elseif(is_array($data)) {
                return $data;
            }
        }
        throw new RuntimeException("Failed to access data from GitHub API");
    }

    public static function getLog() : Log {
        global $log;
        return $log;
    }

    public static function showTime() {
        global $startEvalTime;
        header("X-Status-Execution-Time: " . (microtime(true) - $startEvalTime));
        header("X-Status-cURL-Queries: " . Poggit::$curlCounter);
        header("X-Status-cURL-Time: " . Poggit::$curlTime);
        header("X-Status-MySQL-Queries: " . Poggit::$mysqlCounter);
        header("X-Status-MySQL-Time: " . Poggit::$mysqlTime);
    }
}
