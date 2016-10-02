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

final class Poggit {
    const POGGIT_VERSION = "1.0";

    const PROJECT_TYPE_PLUGIN = 1;
    const PROJECT_TYPE_LIBRARY = 2;

    const BUILD_CLASS_DEV = 1;
    const BUILD_CLASS_BETA = 2;
    const BUILD_CLASS_RELEASE = 3;

    const GH_API_PREFIX = "https://api.github.com/";

    public static $PROJECT_TYPE_HUMAN = [
        self::PROJECT_TYPE_PLUGIN => "Plugin",
        self::PROJECT_TYPE_LIBRARY => "Library"
    ];
    public static $BUILD_CLASS_HUMAN = [
        self::BUILD_CLASS_DEV => "Dev",
        self::BUILD_CLASS_BETA => "Beta",
        self::BUILD_CLASS_RELEASE => "Release"
    ];

    public static $curlCounter = 0;
    public static $curlTime = 0;
    public static $mysqlCounter = 0;
    public static $mysqlTime = 0;

    public static $plainTextOutput = false;

    public static $lastCurlHeaders;

    /**
     * Returns the internally absolute path to Poggit site.
     *
     * Example return value: <code>/poggit/</code>
     *
     * @return string
     */
    public static function getRootPath() : string {
        return "/" . trim(Poggit::getSecret("paths.url"), "/") . "/";
    }

    public static function getSecret(string $name) {
        global $secretsCache;
        if(!isset($secretsCache)) {
            $secretsCache = json_decode($path = file_get_contents(SECRET_PATH . "secrets.json"), true);
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
            Poggit::getLog()->v("Executing MySQL query $query with args $types: " . json_encode($args));
            $stmt->bind_param($types, ...$args);
            if(!$stmt->execute()) {
                throw new RuntimeException("Failed to execute query: " . $db->error);
            }
            $result = $stmt->get_result();
        } else {
            Poggit::getLog()->v("Executing MySQL query $query");
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
        $headers = ["User-Agent: Poggit/" . Poggit::POGGIT_VERSION];
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
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $startTime = microtime(true);
        $ret = curl_exec($ch);
        $headerLength = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        self::$lastCurlHeaders = substr($ret, 0, $headerLength);
        $ret = substr($ret, $headerLength);
        $endTime = microtime(true);
        self::$curlTime += $endTime - $startTime;
        curl_close($ch);
        Poggit::getLog()->v("cURL $method: $url, returned content of " . strlen($ret) . " bytes");
        return $ret;
    }

    public static function curlPost(string $url, $postFields, string ...$extraHeaders) {
        self::$curlCounter++;
        $headers = ["User-Agent: Poggit/" . Poggit::POGGIT_VERSION];
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
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $startTime = microtime(true);
        $ret = curl_exec($ch);
        $headerLength = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        self::$lastCurlHeaders = substr($ret, 0, $headerLength);
        $ret = substr($ret, $headerLength);
        $endTime = microtime(true);
        self::$curlTime += $endTime - $startTime;
        curl_close($ch);
        Poggit::getLog()->v("cURL POST: $url, returned content of " . strlen($ret) . " bytes");
        return $ret;
    }

    public static function curlGet(string $url, string ...$extraHeaders) {
        self::$curlCounter++;
        $headers = ["User-Agent: Poggit/" . Poggit::POGGIT_VERSION];
        foreach($extraHeaders as $header) {
            $headers[] = $header;
        }
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $startTime = microtime(true);
        $ret = curl_exec($ch);
        $headerLength = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        self::$lastCurlHeaders = substr($ret, 0, $headerLength);
        $ret = substr($ret, $headerLength);
        $endTime = microtime(true);
        self::$curlTime += $endTime - $startTime;
        curl_close($ch);
        Poggit::getLog()->v("cURL GET: $url, returned content of " . strlen($ret) . " bytes");
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
        $data = Poggit::curl("https://api.github.com/" . $url, $postFields, $customMethod, ...$headers);
        if(is_string($data)) {
            self::parseGhApiHeaders();
            $data = json_decode($data);
            if(is_object($data)) {
                if(!isset($data->message, $data->documentation_url)) {
                    return $data;
                }
                throw new GitHubAPIException($data);
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
        $data = Poggit::curlPost("https://api.github.com/" . $url, $postFields, ...$headers);
        if(is_string($data)) {
            self::parseGhApiHeaders();
            $data = json_decode($data);
            if(is_object($data)) {
                if(!isset($data->message, $data->documentation_url)) {
                    return $data;
                }
                throw new GitHubAPIException($data);
            } elseif(is_array($data)) {
                return $data;
            }
        }
        throw new RuntimeException("Failed to access data from GitHub API: " . json_encode($data));
    }

    public static function ghApiGet(string $url, string $token = "", bool $customAccept = false, bool $nonJson = false) {
        $headers = [];
        if($customAccept) {
            $headers[] = EARLY_ACCEPT;
        }
        if($token) {
            $headers[] = "Authorization: bearer $token";
        }
        $curl = Poggit::curlGet(self::GH_API_PREFIX . $url, ...$headers);
        if(is_string($curl)) {
            $recvHeaders = self::parseGhApiHeaders();
            if($nonJson) {
                return $curl;
            }
            $data = json_decode($curl);
            if(is_object($data)) {
                if(!isset($data->message, $data->documentation_url)) {
                    return $data;
                }
                throw new GitHubAPIException($data);
            } elseif(is_array($data)) {
                if(isset($recvHeaders["Link"])) {
                    if(preg_match('%<(https://[^>]+)>; rel="next"%', $recvHeaders["Link"], $match)) {
                        $link = $match[1];
                        if(substr($link, 0, $pfxLen = strlen(self::GH_API_PREFIX)) === self::GH_API_PREFIX) {
                            $link = substr($link, $pfxLen);
                            $data = array_merge($data, Poggit::ghApiGet($link, $token, $customAccept));
                        }
                    }
                }
                return $data;
            }
            throw new RuntimeException("Malformed data from GitHub API");
        }
        throw new RuntimeException("Failed to access data from GitHub API");
    }

    private static function parseGhApiHeaders() {
        $headers = [];
        foreach(explode("\n", self::$lastCurlHeaders) as $header) {
            $kv = explode(": ", $header);
            if(count($kv) !== 2) continue;
            $headers[$kv[0]] = $kv[1];
        }
        if(isset($headers["X-RateLimit-Remaining"])) {
            try {
                /** @noinspection PhpUsageOfSilenceOperatorInspection */
                @header("X-GitHub-RateLimit-Remaining", $headers["X-RateLimit-Remaining"]);
            } catch(\Exception $e) {
            }
        }
        return $headers;
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

    public static function getTmpFile($ext = ".tmp") : string {
        $tmpDir = rtrim(self::getSecret("meta.tmpPath") ?: sys_get_temp_dir(), "/") . "/";
        $file = tempnam($tmpDir, $ext);
//        do {
//            $file = $tmpDir . bin2hex(random_bytes(4)) . $ext;
//        } while(is_file($file));
//        register_shutdown_function("unlink", $file);
        return $file;
    }

    public static function checkDeps() {
//        assert(function_exists("apcu_store"));
        assert(function_exists("curl_init"));
        assert(class_exists(mysqli::class));
        assert(!ini_get("phar.readonly"));
        assert(function_exists("yaml_emit"));
    }

    public static function showBuildNumbers(int $global, int $internal, string $link = "") {
        if(strlen($link) > 0) { ?>
            <a href="<?= Poggit::getRootPath() . $link ?>">
        <?php } ?>
        #<?= $internal ?> (&amp;<?= strtoupper(dechex($global)) ?>)
        <?php if(strlen($link) > 0) { ?>
            </a>
        <?php } ?>
        <sup class="hover-title" title="#<?= $internal ?> is the internal build number for your project.
&<?= strtoupper(dechex($global)) ?> is a unique build ID for all Poggit builds">(?)</sup>
        <?php
    }

    public static function ghLink($url) {
        $markUrl = Poggit::getRootPath() . "res/ghMark.png";
        echo "<a href='$url' target='_blank'>";
        echo "<img class='gh-logo' src='$markUrl' width='16'>";
        echo "</a>";
    }

    private function __construct() {
    }
}
