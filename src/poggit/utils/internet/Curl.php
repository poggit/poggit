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

use poggit\Meta;
use poggit\utils\lang\Lang;
use poggit\utils\lang\TemporalHeaderlessWriter;
use RuntimeException;
use function array_merge;
use function count;
use function curl_close;
use function curl_error;
use function curl_exec;
use function curl_getinfo;
use function curl_init;
use function curl_setopt;
use function explode;
use function file_put_contents;
use function filesize;
use function is_string;
use function microtime;
use function parse_url;
use function strlen;
use function substr;
use function unlink;
use const CURLINFO_HEADER_SIZE;
use const CURLINFO_RESPONSE_CODE;
use const CURLOPT_AUTOREFERER;
use const CURLOPT_BUFFERSIZE;
use const CURLOPT_CONNECTTIMEOUT;
use const CURLOPT_CUSTOMREQUEST;
use const CURLOPT_FOLLOWLOCATION;
use const CURLOPT_FORBID_REUSE;
use const CURLOPT_FRESH_CONNECT;
use const CURLOPT_HEADERFUNCTION;
use const CURLOPT_HTTPHEADER;
use const CURLOPT_NOPROGRESS;
use const CURLOPT_POST;
use const CURLOPT_POSTFIELDS;
use const CURLOPT_PROGRESSFUNCTION;
use const CURLOPT_RETURNTRANSFER;
use const CURLOPT_SSL_VERIFYHOST;
use const CURLOPT_SSL_VERIFYPEER;
use const CURLOPT_TIMEOUT;
use const CURLOPT_WRITEFUNCTION;
use const PHP_EOL;
use const PHP_URL_HOST;

final class Curl {
    public static $curlBody = 0;
    public static $curlRetries = 0;
    public static $curlTime = 0;
    public static $curlCounter = 0;
    public static $lastCurlHeaders;
    public static $lastCurlResponseCode;

    public static function curl(string $url, string $postContents, string $method, string ...$extraHeaders) {
        return self::iCurl($url, function($ch) use ($method, $postContents) {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if(strlen($postContents) > 0) curl_setopt($ch, CURLOPT_POSTFIELDS, $postContents);
        }, ...$extraHeaders);
    }

    public static function curlPost(string $url, $postFields, string ...$extraHeaders) {
        return self::iCurl($url, function($ch) use ($postFields) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        }, ...$extraHeaders);
    }

    public static function curlGet(string $url, string ...$extraHeaders) {
        return self::iCurl($url, function() {
        }, ...$extraHeaders);
    }

    public static function curlGetMaxSize(string $url, int $maxBytes, string ...$extraHeaders) {
        return self::iCurl($url, function($ch) use ($maxBytes) {
            curl_setopt($ch, CURLOPT_BUFFERSIZE, 128);
            curl_setopt($ch, CURLOPT_NOPROGRESS, false);
            /** @noinspection PhpUnusedParameterInspection */
            curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function(/** @noinspection PhpUnusedParameterInspection */
                $ch, $dlSize, $dlAlready, $ulSize, $ulAlready) use ($maxBytes) {
                echo $dlSize, PHP_EOL;
                return $dlSize > $maxBytes ? 1 : 0;
            });
        }, ...$extraHeaders);
    }

    public static function curlToFile(string $url, string $file, int $maxBytes, string ...$extraHeaders) {
        $writer = new TemporalHeaderlessWriter($file);

        self::iCurl($url, function($ch) use ($maxBytes, $writer) {
            curl_setopt($ch, CURLOPT_BUFFERSIZE, 1024);
            curl_setopt($ch, CURLOPT_NOPROGRESS, false);
            /** @noinspection PhpUnusedParameterInspection */
            curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function($ch, $dlSize, $dlAlready, $ulSize, $ulAlready) use ($maxBytes) {
                return $dlSize > $maxBytes ? 1 : 0;
            });
            curl_setopt($ch, CURLOPT_WRITEFUNCTION, [$writer, "write"]);
            curl_setopt($ch, CURLOPT_HEADERFUNCTION, [$writer, "header"]);
        }, ...$extraHeaders);
        self::$lastCurlHeaders = $writer->close();

        if(filesize($file) > $maxBytes) {
            file_put_contents($file, "");
            @unlink($file);
            throw new RuntimeException("File too large");
        }
    }

    public static function iCurl(string $url, callable $configure, string ...$extraHeaders) {
        self::$curlCounter++;
        $headers = array_merge(["User-Agent: Poggit/" . Meta::POGGIT_VERSION], $extraHeaders);
        retry:
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
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, Meta::getCurlTimeout());
        curl_setopt($ch, CURLOPT_TIMEOUT, Meta::getCurlTimeout());
        $configure($ch);
        $startTime = microtime(true);
        $ret = curl_exec($ch);
        $endTime = microtime(true);
        self::$curlTime += $tookTime = $endTime - $startTime;
        if(curl_error($ch) !== "") {
            $error = curl_error($ch);
            curl_close($ch);
            if(Lang::startsWith($error, "Could not resolve host: ")) {
                self::$curlRetries++;
                Meta::getLog()->w("Could not resolve host " . parse_url($url, PHP_URL_HOST) . ", retrying");
                if(self::$curlRetries > 5) throw new CurlErrorException("More than 5 curl host resolve failures in a request");
                self::$curlCounter++;
                goto retry;
            }
            if(Lang::startsWith($error, "Operation timed out after ") or Lang::startsWith($error, "Resolving timed out after ")) {
                self::$curlRetries++;
                Meta::getLog()->w("CURL request timeout for $url");
                if(self::$curlRetries > 5) throw new CurlTimeoutException("More than 5 curl timeouts in a request");
                self::$curlCounter++;
                goto retry;
            }
            throw new CurlErrorException($error);
        }
        self::$lastCurlResponseCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headerLength = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        if(is_string($ret)) {
            self::$lastCurlHeaders = substr($ret, 0, $headerLength);
            $ret = substr($ret, $headerLength);
        }
        self::$curlBody += strlen($ret);
        if(Meta::isDebug()) Meta::getLog()->v("cURL access to $url, took $tookTime, response code " . self::$lastCurlResponseCode);
        return $ret;
    }

    public static function parseHeaders(): array {
        $headers = [];
        foreach(Lang::explodeNoEmpty("\n", self::$lastCurlHeaders) as $header) {
            $kv = explode(": ", $header);
            if(count($kv) !== 2) continue;
            $headers[$kv[0]] = $kv[1];
        }
        if(isset($headers["X-RateLimit-Remaining"])) {
            GitHub::$ghRateRemain = $headers["X-RateLimit-Remaining"];
        }
        return $headers;
    }
}
