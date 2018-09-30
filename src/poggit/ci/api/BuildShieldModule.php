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

namespace poggit\ci\api;

use poggit\account\Session;
use poggit\ci\lint\BuildResult;
use poggit\module\Module;
use poggit\utils\internet\Curl;
use poggit\utils\internet\GitHub;
use poggit\utils\internet\Mysql;
use poggit\utils\lang\Lang;
use stdClass;
use function count;
use function header;
use function str_replace;
use function urlencode;

class BuildShieldModule extends Module {
    public function output() {
        $parts = Lang::explodeNoEmpty("/", $this->getQuery(), 4);
        if(count($parts) < 3) $this->errorBadRequest("Correct syntax: <code class='code'>ci.shield/:owner/:repo/:project{/:branch}</code>", false);
        list($owner, $repo, $project) = $parts;
        if($project === "~") $project = $repo;
        $hasBranch = isset($parts[3]);
        $branchQueryPart = $hasBranch ? " AND builds.branch = ? " : " ";

        $rows = Mysql::query("SELECT builds.buildId, repos.private FROM builds
            INNER JOIN projects ON projects.projectId = builds.projectId
            INNER JOIN repos ON projects.repoId = repos.repoId
            WHERE (repos.owner = ? AND repos.name = ? AND projects.name = ? $branchQueryPart)
            ORDER BY builds.created DESC LIMIT 1", "sss" . ($hasBranch ? "s" : ""),
            ...($hasBranch ? [$owner, $repo, $project, $parts[3]] : [$owner, $repo, $project]));
        if(count($rows) === 0) $this->errorNotFound(true);
        $row = $rows[0];
        if((int) $row["private"]) {
            if(isset($_REQUEST["access_token"])) {
                $token = $_REQUEST["access_token"];
            } else {
                $token = Session::getInstance()->getAccessToken();
                if($token === "") $this->errorNotFound(true);
            }
            $result = GitHub::ghApiGet("repos/$owner/$repo", $token);
            if(!($result instanceof stdClass) or !isset($result->permissions) or !$result->permissions->pull) {
                $this->errorNotFound(true); // quite vulnerable to time attacks, but I don't care
            }
        }
        $rows = Mysql::query("SELECT level, COUNT(*) AS cnt FROM builds_statuses WHERE buildId = ?
            GROUP BY level DESC LIMIT 1", "i", $row["buildId"]);
        if(isset($rows[0])) {
            $level = (int) $rows[0]["level"];
            static $colors = [
                BuildResult::LEVEL_LINT => "yellowgreen",
                BuildResult::LEVEL_WARN => "yellow",
                BuildResult::LEVEL_ERROR => "orange",
                BuildResult::LEVEL_BUILD_ERROR => "red",
            ];
            static $names = [
                BuildResult::LEVEL_LINT => "lint",
                BuildResult::LEVEL_WARN => "warning",
                BuildResult::LEVEL_ERROR => "error",
                BuildResult::LEVEL_BUILD_ERROR => "build error",
            ];
            $cnt = (int) $rows[0]["cnt"];
            $url = "https://img.shields.io/badge/" . urlencode("poggit") . "-" .
                str_replace("+", "%20", urlencode("$cnt " . $names[$level] . ($cnt > 1 ? "s" : ""))) . "-" . $colors[$level] .
                ".svg?style=" . ($_REQUEST["style"] ?? "flat");
        } else {
            $url = "https://img.shields.io/badge/poggit-passing-brightgreen.svg?style=" . ($_REQUEST["style"] ?? "flat");
        }

        header("Content-Type: image/svg+xml;charset=utf-8");
        header("Cache-Control: no-cache");
        echo Curl::curlGet($url);
    }
}
