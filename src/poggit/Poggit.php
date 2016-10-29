<?php

/*
 * Poggit
 *
 * Copyright (C) 2016 Poggit
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
use poggit\exception\CurlErrorException;
use poggit\exception\GitHubAPIException;
use poggit\module\error\InternalErrorPage;
use poggit\output\OutputManager;
use RuntimeException;
use stdClass;

final class Poggit {
    const POGGIT_VERSION = "1.0";

    const PROJECT_TYPE_PLUGIN = 1;
    const PROJECT_TYPE_LIBRARY = 2;

    const BUILD_CLASS_DEV = 1;
    const BUILD_CLASS_BETA = 2;
    const BUILD_CLASS_RELEASE = 3;
    const BUILD_CLASS_PR = 4;

    const GH_API_PREFIX = "https://api.github.com/";

    const CURL_CONN_TIMEOUT = 10;
    const CURL_TIMEOUT = 10;

    public static $PROJECT_TYPE_HUMAN = [
        self::PROJECT_TYPE_PLUGIN => "Plugin",
        self::PROJECT_TYPE_LIBRARY => "Library"
    ];
    public static $BUILD_CLASS_HUMAN = [
        self::BUILD_CLASS_DEV => "Dev",
        self::BUILD_CLASS_BETA => "Beta",
        self::BUILD_CLASS_RELEASE => "Release",
        self::BUILD_CLASS_PR => "PR"
    ];
    public static $BUILD_CLASS_IDEN = [
        self::BUILD_CLASS_DEV => "dev",
        self::BUILD_CLASS_BETA => "beta",
        self::BUILD_CLASS_RELEASE => "rc",
        self::BUILD_CLASS_PR => "pr"
    ];

    public static $curlCounter = 0;
    public static $curlTime = 0;
    public static $mysqlCounter = 0;
    public static $mysqlTime = 0;

    public static $plainTextOutput = false;

    public static $lastCurlResponseCode;
    public static $lastCurlHeaders;
    public static $ghRateRemain;

    /**
     * Returns the internally absolute path to Poggit site.
     *
     * Example return value: <code>/poggit/</code>
     *
     * @return string
     */
    public static function getRootPath() : string {
        // by splitting into two trim calls, only one slash will be returned for empty meta.intPath value
        return rtrim("/" . ltrim(Poggit::getSecret("meta.intPath"), "/"), "/") . "/";
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
        $parts = array_filter(explode(".", $name));
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

    public static function getLog() : Log {
        global $log;
        return $log;
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
        return self::iCurl($url, function ($ch) use ($method, $postContents) {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if(strlen($postContents) > 0) curl_setopt($ch, CURLOPT_POSTFIELDS, $postContents);
        }, ...$extraHeaders);
    }

    public static function curlPost(string $url, $postFields, string ...$extraHeaders) {
        return self::iCurl($url, function ($ch) use ($postFields) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        }, ...$extraHeaders);
    }

    public static function curlGet(string $url, string ...$extraHeaders) {
        return self::iCurl($url, function () {
        }, ...$extraHeaders);
    }

    private static function iCurl(string $url, callable $configure, string ...$extraHeaders) {
        self::$curlCounter++;
        $headers = array_merge(["User-Agent: Poggit/" . Poggit::POGGIT_VERSION], $extraHeaders);
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
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::CURL_CONN_TIMEOUT);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::CURL_TIMEOUT);
        $configure($ch);
        $startTime = microtime(true);
        $ret = curl_exec($ch);
        $endTime = microtime(true);
        self::$curlTime += $tookTime = $endTime - $startTime;
        if(curl_error($ch) !== "") throw new CurlErrorException(curl_error($ch));
        self::$lastCurlResponseCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headerLength = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        self::$lastCurlHeaders = substr($ret, 0, $headerLength);
        $ret = substr($ret, $headerLength);
        Poggit::getLog()->v("cURL access to $url, took $tookTime, response code " . self::$lastCurlResponseCode);
        return $ret;
    }

    public static function ghApiCustom(string $url, string $customMethod, $postFields, string $token = "", bool $nonJson = false) {
        $headers = ["Authorization: bearer " . ($token === "" ? self::getSecret("app.defaultToken") : $token)];
        $data = Poggit::curl("https://api.github.com/" . $url, json_encode($postFields), $customMethod, ...$headers);
        return self::processGhApiResult($data, $url, $token, $nonJson);
    }

    public static function ghApiPost(string $url, $postFields, string $token = "", bool $nonJson = false) {
        $headers = ["Authorization: bearer " . ($token === "" ? self::getSecret("app.defaultToken") : $token)];
        $data = Poggit::curlPost("https://api.github.com/" . $url, $encodedPost = json_encode($postFields, JSON_UNESCAPED_SLASHES), ...$headers);
        return self::processGhApiResult($data, $url, $token, $nonJson);
    }

    /**
     * @param string $url
     * @param string $token
     * @param bool   $nonJson
     * @return stdClass|array|string
     */
    public static function ghApiGet(string $url, string $token, bool $nonJson = false) {
        $headers = ["Authorization: bearer " . ($token === "" ? self::getSecret("app.defaultToken") : $token)];
        $curl = Poggit::curlGet(self::GH_API_PREFIX . $url, ...$headers);
        return self::processGhApiResult($curl, $url, $token, $nonJson);
    }

    private static function processGhApiResult($curl, string $url, string $token, bool $nonJson = false) {
        if(is_string($curl)) {
            $recvHeaders = self::parseGhApiHeaders();
            if($nonJson) {
                return $curl;
            }
            $data = json_decode($curl);
            if(is_object($data)) {
                if(self::$lastCurlResponseCode < 400) return $data;
                throw new GitHubAPIException($data);
            }
            if(is_array($data)) {
                if(isset($recvHeaders["Link"]) and preg_match('%<(https://[^>]+)>; rel="next"%', $recvHeaders["Link"], $match)) {
                    $link = $match[1];
                    assert(Poggit::startsWith($link, self::GH_API_PREFIX));
                    $link = substr($link, strlen(self::GH_API_PREFIX));
                    $data = array_merge($data, Poggit::ghApiGet($link, $token));
                }
                return $data;
            }
            throw new RuntimeException("Malformed data from GitHub API: " . json_encode($data));
        }
        throw new RuntimeException("Failed to access data from GitHub API: $url, ", substr($token, 0, 7), ", ", json_encode($curl));
    }

    private static function parseGhApiHeaders() {
        $headers = [];
        foreach(array_filter(explode("\n", self::$lastCurlHeaders)) as $header) {
            $kv = explode(": ", $header);
            if(count($kv) !== 2) continue;
            $headers[$kv[0]] = $kv[1];
        }
        if(isset($headers["X-RateLimit-Remaining"])) {
            self::$ghRateRemain = $headers["X-RateLimit-Remaining"];
        }
        return $headers;
    }

    public static function showBuildNumbers(int $global, int $internal, string $link = "") {
        if(strlen($link) > 0) { ?>
            <a href="<?= Poggit::getRootPath() . $link ?>">
        <?php } ?>
        <span style='font-family:"Courier New", monospace;'>
            #<?= $internal ?> (&amp;<?= strtoupper(dechex($global)) ?>)
        </span>
        <?php if(strlen($link) > 0) { ?>
            </a>
        <?php } ?>
        <sup class="hover-title" title="#<?= $internal ?> is the internal build number for your project.
&amp;<?= strtoupper(dechex($global)) ?> is a unique build ID for all Poggit CI builds">(?)</sup>
        <?php
    }

    public static function ghLink($url) {
        $markUrl = Poggit::getRootPath() . "res/ghMark.png";
        echo "<a href='$url' target='_blank'>";
        echo "<img class='gh-logo' src='$markUrl' width='16'>";
        echo "</a>";
    }

    /**
     * @param string|stdClass $owner
     * @param string|int      $avatar
     * @param int             $avatarWidth
     */
    public static function displayUser($owner, $avatar = "", $avatarWidth = 16) {
        if($owner instanceof stdClass) {
            self::displayUser($owner->login, $owner->avatar_url, $avatar ?: 16);
            return;
        }
        if($avatar !== "") {
            echo "<img src='$avatar' width='$avatarWidth'> ";
        }
        echo $owner, " ";
        Poggit::ghLink("https://github.com/$owner");
    }

    public static function displayRepo(string $owner, string $repo, string $avatar = "", int $avatarWidth = 16) {
        Poggit::displayUser($owner, $avatar, $avatarWidth);
        echo " / ";
        echo $repo, " ";
        Poggit::ghLink("https://github.com/$owner/$repo");
    }

    public static function displayAnchor($name) {
        ?>
        <a class="dynamic-anchor" name="<?= $name ?>" href="#<?= $name ?>">&sect;</a>
        <?php
    }

    public static function showStatus() {
        global $startEvalTime;
        header("X-Status-Execution-Time: " . sprintf("%f", (microtime(true) - $startEvalTime)));
        header("X-Status-cURL-Queries: " . Poggit::$curlCounter);
        header("X-Status-cURL-Time: " . sprintf("%f", Poggit::$curlTime));
        header("X-Status-MySQL-Queries: " . Poggit::$mysqlCounter);
        header("X-Status-MySQL-Time: " . sprintf("%f", Poggit::$mysqlTime));
        if(isset(self::$ghRateRemain)) {
            header("X-GitHub-RateLimit-Remaining: " . self::$ghRateRemain);
        }
    }

    public static function startsWith(string $string, string $prefix) : bool {
        return strlen($string) >= strlen($prefix) and substr($string, 0, strlen($prefix)) === $prefix;
    }

    public static function endsWith(string $string, string $suffix) : bool {
        return strlen($string) >= strlen($suffix) and substr($string, -strlen($suffix)) === $suffix;
    }

    public static function copyToObject($source, $object) {
        foreach($source as $k => $v) {
            $object->{$k} = $v;
        }
    }

    public static function checkDeps() {
//        assert(function_exists("apcu_store"));
        assert(function_exists("curl_init"));
        assert(class_exists(mysqli::class));
        assert(!ini_get("phar.readonly"));
        assert(function_exists("yaml_emit"));
    }

    public static function isDebug() : bool {
        return Poggit::getSecret("meta.debug");
    }

    private function __construct() {
    }
}
