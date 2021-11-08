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

namespace poggit\resource;

use poggit\account\Session;
use poggit\Meta;
use poggit\module\Module;
use poggit\utils\internet\GitHub;
use poggit\utils\internet\GitHubAPIException;
use poggit\utils\internet\Mysql;
use poggit\utils\lang\Lang;
use poggit\utils\OutputManager;
use RuntimeException;
use function apache_request_headers;
use function array_merge;
use function date;
use function file_get_contents;
use function filesize;
use function header;
use function http_response_code;
use function is_file;
use function is_numeric;
use function json_decode;
use function json_encode;
use function md5_file;
use function readfile;
use function session_name;
use function sha1_file;
use function strpos;
use function substr;
use const DATE_RFC7231;
use const JSON_UNESCAPED_SLASHES;

class ResourceGetModule extends Module {
    private function error(int $httpCode, string $error, string $message, array $extraData = []) {
        OutputManager::terminateAll();
        http_response_code($httpCode);
        header("Content-Type: application/json");
        echo json_encode(array_merge([
            "error" => $error,
            "message" => $message
        ], $extraData), JSON_UNESCAPED_SLASHES);
        die;
    }

    public function output() {
        $query = $this->getQuery();
        $pos = strpos($query, "/");
        $idStr = $pos === false ? $query : substr($query, 0, $pos);
        $afterId = $pos === false ? "" : ("/" . substr($query, $pos + 1));
        $md = false;
        if(!is_numeric($idStr)) {
            if(!Lang::endsWith($idStr, ".md")) $this->errorNotFound(true);
            $idStr = substr($idStr, 0, -3);
            $md = true;
            if(!is_numeric($idStr)) $this->errorNotFound(true);
        }
        $rsrId = (int) $idStr;
        if($rsrId === ResourceManager::NULL_RESOURCE) {
            http_response_code(410);
            die;
        }
        $res = Mysql::query("SELECT type, mimeType, IFNULL(relMd, 0) AS relMd, accessFilters,
            unix_timestamp(created) AS lastMod,
            unix_timestamp(created) + duration - unix_timestamp(CURRENT_TIMESTAMP(3)) AS remaining,
            unix_timestamp(created) + duration AS expires
            FROM resources WHERE resourceId = ?", "i", $rsrId);
        if(!isset($res[0])) $this->error(404, "Resource.NotFound", "There is no resource associated with this ID");
        $res = $res[0];
        $type = $res["type"];
        $remaining = (float) $res["remaining"];
        $accessFilters = json_decode($res["accessFilters"]);
        $relMd = $res["relMd"];
        if($remaining < 0) $this->error(410, "Expired", "Resource has expired and is deleted", ["seconds" => -$remaining]);
        if($md and $relMd !== 0) {
            http_response_code(301); // permanent redirection to
            header("Cache-Control: public");
            Meta::redirect("r/" . $relMd . $afterId);
        }
        $accessToken = "";
        if(isset($_COOKIE["PoggitSess"])) $accessToken = Session::getInstance()->getAccessToken();
        if(isset($_REQUEST["access_token"])) $accessToken = $_REQUEST["access_token"];
        $headers = apache_request_headers();
        if(isset($headers["Authorization"])) {
            $auth = $headers["Authorization"];
            $accessToken = ($pos = strpos($auth, " ")) !== false ? substr($auth, $pos + 1) : $auth;
        }
        // blacklists
        foreach($accessFilters as $filter) {
            if($filter->type === "repoAccess") {
                $repo = $filter->repo;
                try {
                    $data = GitHub::ghApiGet("repositories/$repo->id", $accessToken ?: Meta::getDefaultToken());
                } catch(GitHubAPIException $e) {
                    $this->error(401, "AccessFilter.RepoNotFound",
                        "Access to repo #$repo->id ($repo->owner/$repo->name) required. " .
                        "The repo is deleted or private to the provided access token. " .
                        "Access tokens can be provided using the Authorization header.", ["repo" => $repo]);
                    die;
                }
                foreach($repo->requiredPerms as $perm) {
                    if(!$data->permissions->{$perm}) {
                        $this->error(401, "AccessFilter.PermDenied",
                            "Provided access token does not have $perm access to repo $data->full_name. " .
                            "Access tokens can be provided using the Authorization header.", ["repo" => $repo]);
                    }
                }

            }
        }
        $file = ResourceManager::pathTo($rsrId, $type);
        if(!is_file($file)) $this->error(410, "Resource.NotFound", "The resource is invalid and cannot be accessed");
        OutputManager::terminateAll();
        header("Last-Modified: " . date(DATE_RFC7231, $res["lastMod"]));
        header("Expires: " . date(DATE_RFC7231, $res["expires"]));
        if(Meta::getModuleName() === "r.md5") {
            header("Content-Type: text/plain");
            echo md5_file($file);
        } elseif(Meta::getModuleName() === "r.sha1") {
            header("Content-Type: text/plain");
            echo sha1_file($file);
        } elseif(Lang::startsWith($_SERVER["HTTP_ACCEPT"] ?? "", "text/plain") and $res["mimeType"] === "text/plain") {
            header("Content-Type: text/plain");
            echo file_get_contents($file);
        } else {
            header("Content-Type: " . $res["mimeType"]);
            header("Content-Length: " . filesize($file));
            readfile($file);
        }
        try {
            Mysql::query("SELECT IncRsrDlCnt(?, ?)", "is", $rsrId, Meta::getClientIP());
        } catch(RuntimeException $e) {
        }
        die;
    }
}
