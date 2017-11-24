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

use poggit\Meta;
use poggit\utils\lang\Lang;
use poggit\utils\lang\TemporalHeaderlessWriter;
use RuntimeException;
use stdClass;

final class Curl {
    const GH_API_PREFIX = "https://api.github.com/";
    const GH_NOT_FOUND = /** @lang JSON */
        '{"message":"Not Found","documentation_url":"https://developer.github.com/v3"}';
    const TEMP_PERM_CACHE_KEY_PREFIX = "poggit.CurlUtils.testPermission.cache.";

    public static $curlBody = 0;
    public static $curlRetries = 0;
    public static $curlTime = 0;
    public static $curlCounter = 0;
    public static $lastCurlHeaders;
    public static $lastCurlResponseCode;
    public static $ghRateRemain;

    private static $tempPermCache;

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
            curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function($ch, $dlSize, $dlAlready, $ulSize, $ulAlready) use ($maxBytes) {
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
        self::$lastCurlResponseCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headerLength = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        if(is_string($ret)) {
            self::$lastCurlHeaders = substr($ret, 0, $headerLength);
            $ret = substr($ret, $headerLength);
        }
        self::$curlBody += strlen($ret);
        Meta::getLog()->v("cURL access to $url, took $tookTime, response code " . self::$lastCurlResponseCode);
        return $ret;
    }

    public static function ghApiCustom(string $url, string $customMethod, $postFields, string $token = "", bool $nonJson = false, array $moreHeaders = ["Accept: application/vnd.github.v3+json"]) {
        if($token !== "") {
            $moreHeaders[] = "Authorization: bearer " . $token;
        }
        $data = self::curl("https://api.github.com/" . $url, json_encode($postFields), $customMethod, ...$moreHeaders);
        return self::processGhApiResult($data, $url, $token, $nonJson);
    }

    public static function ghApiPost(string $url, $postFields, string $token = "", bool $nonJson = false, array $moreHeaders = ["Accept: application/vnd.github.v3+json"]) {
        if($token !== "") {
            $moreHeaders[] = "Authorization: bearer " . $token;
        }
        $data = self::curlPost("https://api.github.com/" . $url, $encodedPost = json_encode($postFields, JSON_UNESCAPED_SLASHES), ...$moreHeaders);
        return self::processGhApiResult($data, $url, $token, $nonJson);
    }

    /**
     * @param string        $url
     * @param string        $token
     * @param array         $moreHeaders
     * @param bool          $nonJson
     * @param callable|null $shouldLinkMore
     * @return array|stdClass|string
     */
    public static function ghApiGet(string $url, string $token, array $moreHeaders = ["Accept: application/vnd.github.v3+json"], bool $nonJson = false, callable $shouldLinkMore = null) {
        if($token !== "") {
            $moreHeaders[] = "Authorization: bearer " . $token;
        }
        $curl = self::curlGet(self::GH_API_PREFIX . $url, ...$moreHeaders);
        return self::processGhApiResult($curl, $url, $token, $nonJson, $shouldLinkMore);
    }

    public static function ghGraphQL(string $query, string $token, array $vars) {
//        Meta::getLog()->d("GraphQL: " . preg_replace('/[ \t\n\r]+/', ' ', $query));
//        Meta::getLog()->jd($vars);
        $result = self::ghApiPost("graphql", ["query" => $query, "variables" => $vars], $token);
//        Meta::getLog()->jd($result);
        return $result;
    }

    /**
     * @param string $token
     * @param string $extraFields
     * @return stdClass[]
     */
    public static function listMyRepos(string $token, string $extraFields = ""): array {
        $firstQuery = 'query ($s: Int) {
            viewer {
                repositories(first: $s) {
                    pageInfo {
                        endCursor
                        hasNextPage
                    }
                    edges {
                        node {
                            id: databaseId
                            owner { login avatar_url: avatarUrl }
                            name
                            %extraFields%
                        }
                    }
                }
            }
        }';
        $secondQuery = 'query ($s: Int, $a: String) {
            viewer {
                repositories(first: $s, after: $a) {
                    pageInfo {
                        endCursor
                        hasNextPage
                    }
                    edges {
                        node {
                            id: databaseId
                            owner { login avatar_url: avatarUrl }
                            name
                            %extraFields%
                        }
                    }
                }
            }
        }';
        $output = [];
        $first = true;
        $vars = ["s" => Meta::getCurlPerPage()];
        do {
            $result = self::ghGraphQL(str_replace('%extraFields%', $extraFields, $first ? $firstQuery : $secondQuery), $token, $vars);
            if(empty($output) && !isset($result->data->viewer)){
                return [];
            }
            $vars["a"] = $result->data->viewer->repositories->pageInfo->endCursor;
            foreach($result->data->viewer->repositories->edges as $edge) {
                $node = $edge->node;
                if(isset($node->owner) && isset($node->owner->login, $node->name)) {
                    $node->full_name = "{$node->owner->login}/$node->name";
                }
                $output[$node->id] = $node;
            }
            $first = false;
        } while($result->data->viewer->repositories->pageInfo->hasNextPage);
        return $output;
    }

    /**
     * @param string $user
     * @param string $token
     * @param string $fields
     * @return stdClass[]
     */
    public static function listHisRepos(string $user, string $token, string $fields): array {
        $firstQuery = 'query ($s: Int, $u: String!) {
            user: repositoryOwner(login: $u) {
                repositories(first: $s) {
                    pageInfo {
                        endCursor
                        hasNextPage
                    }
                    edges {
                        node {
                            %extraFields%
                        }
                    }
                }
            }
        }';
        $secondQuery = 'query ($s: Int, $u: String!, $a: String) {
            user(login: $u) {
                repositories(first: $s, after: $a) {
                    pageInfo {
                        endCursor
                        hasNextPage
                    }
                    edges {
                        node {
                            %extraFields%
                        }
                    }
                }
            }
        }';
        $output = [];
        $first = true;
        $vars = ["s" => Meta::getCurlPerPage(), "u" => $user];
        do {
            $result = self::ghGraphQL(str_replace('%extraFields%', $fields, $first ? $firstQuery : $secondQuery), $token,
                $vars);
            if(empty($output) && !isset($result->data->user)){
                return [];
            }
            $vars["a"] = $result->data->user->repositories->pageInfo->endCursor;
            foreach($result->data->user->repositories->edges as $edge) {
                $node = $edge->node;
                if(isset($node->owner) && isset($node->owner->login, $node->name)) {
                    $node->full_name = "{$node->owner->login}/$node->name";
                }
                $output[$node->id] = $node;
            }
            $first = false;
        } while($result->data->user->repositories->pageInfo->hasNextPage);
        return $output;
    }

    public static function clearGhUrls($response, ...$except) {
        if(is_array($response)) {
            foreach($response as $value) {
                self::clearGhUrls($value, ...$except);
            }
            return;
        }
        if(!is_object($response)) return;
        foreach($response as $name => $value) {
            if(is_array($value) || is_object($value)) {
                self::clearGhUrls($value, ...$except);
            } elseif(!in_array($name, $except, true) and is_string($value) || $value === null and $name === "url" || substr($name, -4) === "_url") {
                unset($response->{$name});
            }
        }
    }

    public static function processGhApiResult($curl, string $url, string $token, bool $nonJson = false, callable $shouldLinkMore = null) {
        if(is_string($curl)) {
            if($curl === self::GH_NOT_FOUND) throw new GitHubAPIException($url, json_decode($curl));
            $recvHeaders = self::parseHeaders();
            if($nonJson) return $curl;
            $data = json_decode($curl);
            if(is_object($data)) {
                if(self::$lastCurlResponseCode < 400) return $data;
                throw new GitHubAPIException($url, $data);
            }
            if(is_array($data)) {
                if(isset($recvHeaders["Link"]) and preg_match('%<(https://[^>]+)>; rel="next"%', $recvHeaders["Link"], $match)) {
                    $link = $match[1];
                    assert(Lang::startsWith($link, self::GH_API_PREFIX));
                    $link = substr($link, strlen(self::GH_API_PREFIX));
                    if($shouldLinkMore === null or $shouldLinkMore($data)) {
                        $data = array_merge($data, self::ghApiGet($link, $token));
                    }
                }
                return $data;
            }
            throw new RuntimeException("Malformed data from GitHub API: " . json_last_error_msg() . ", " . json_encode($curl) . ", " . json_encode($data));
        }
        throw new RuntimeException("Failed to access data from GitHub API: $url, " . substr($token, 0, 7) . ", " . json_encode($curl));
    }

    public static function parseHeaders(): array {
        $headers = [];
        foreach(Lang::explodeNoEmpty("\n", self::$lastCurlHeaders) as $header) {
            $kv = explode(": ", $header);
            if(count($kv) !== 2) continue;
            $headers[$kv[0]] = $kv[1];
        }
        if(isset($headers["X-RateLimit-Remaining"])) {
            self::$ghRateRemain = $headers["X-RateLimit-Remaining"];
        }
        return $headers;
    }

    public static function testPermission($repoIdentifier, string $token, string $user, string $permName, bool $force = false): bool {
        $user = strtolower($user);
        if($permName !== "admin" && $permName !== "push" && $permName !== "pull") throw new \InvalidArgumentException("Invalid permission name");


        $internalKey = "$user@$repoIdentifier";
        $apcuKey = self::TEMP_PERM_CACHE_KEY_PREFIX . $internalKey;

        if(!$force) {
            if(isset(self::$tempPermCache[$internalKey])) {
                return self::$tempPermCache[$internalKey]->{$permName};
            }
            if(apcu_exists($apcuKey)) {
                self::$tempPermCache[$internalKey] = apcu_fetch($apcuKey);
                return self::$tempPermCache[$internalKey]->{$permName};
            }
        }

        $anotherKey = null;
        try {
            $repository = self::ghApiGet(is_numeric($repoIdentifier) ? "repositories/$repoIdentifier" : "repos/$repoIdentifier", $token);
            $anotherKey = is_numeric($repoIdentifier) ? ($repository->full_name ?? null) : ($repository->id ?? null);
            $value = $repository->permissions ?? (object) ["admin" => false, "push" => false, "pull" => true];
        } catch(GitHubAPIException $e) {
            $value = (object) ["admin" => false, "push" => false, "pull" => false];
        }

        self::$tempPermCache[$internalKey] = $value;
        apcu_store($apcuKey, $value, 604800);
        if($anotherKey !== null) {
            self::$tempPermCache[$anotherKey] = $value;
            apcu_store(self::TEMP_PERM_CACHE_KEY_PREFIX . $anotherKey, $value, 604800);
        }
        return $value->{$permName};
    }
}
