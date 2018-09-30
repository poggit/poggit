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
use poggit\ci\builder\ProjectBuilder;
use poggit\ci\lint\BuildResult;
use poggit\module\Module;
use poggit\utils\internet\GitHub;
use poggit\utils\internet\Mysql;
use poggit\utils\lang\Lang;
use stdClass;
use function count;
use function header;
use function imagecolorallocate;
use function imagecolorallocatealpha;
use function imagecolortransparent;
use function imagecreatetruecolor;
use function imagedestroy;
use function imagefill;
use function imagefilledrectangle;
use function imagepng;
use function imagestring;
use function strtolower;

class BuildBadgeModule extends Module {
    public function output() {
        $parts = Lang::explodeNoEmpty("/", $this->getQuery(), 4);
        if(count($parts) < 3) $this->errorBadRequest("Correct syntax: <code class='code'>ci.badge/:owner/:repo/:project{/:branch}</code>", false);
        list($owner, $repo, $project) = $parts;
        if($project === "~") $project = $repo;
        $types = "sss";
        $args = [$owner, $repo, $project];
        if(isset($parts[3])) {
            $branchQueryPart = " AND builds.branch = ?";
            $types .= "s";
            $args[] = $parts[3];
        } elseif(isset($_GET["build"])) {
            $branchQueryPart = " AND builds.internal = ? AND builds.class = ?";
            $types .= "ii";
            $args[] = (int) $_GET["build"];
            $args[] = isset($_GET["class"]) && strtolower($_GET["class"]) === "pr" ? ProjectBuilder::BUILD_CLASS_PR : ProjectBuilder::BUILD_CLASS_DEV;
        } else {
            $branchQueryPart = "";
        }

        $rows = Mysql::query("SELECT builds.buildId, repos.private FROM builds 
            INNER JOIN projects ON projects.projectId = builds.projectId
            INNER JOIN repos ON projects.repoId = repos.repoId
            WHERE repos.owner = ? AND repos.name = ? AND projects.name = ?
            $branchQueryPart
            ORDER BY builds.created DESC LIMIT 1", $types, ...$args);
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
        $statuses = BuildResult::fetchMysql((int) $row["buildId"])->statuses;
        $levels = [];
        foreach($statuses as $status) {
            $levels[$status->level] = ($levels[$status->level] ?? 0) + 1;
        }
        if(count($levels) === 0) {
            $levels[BuildResult::LEVEL_OK] = "PASSED";
        }

        header("Content-Type: image/png");
        $img = imagecreatetruecolor(125, 20 * (1 + count($levels)));
        $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
        imagecolortransparent($img, $transparent);
        imagefill($img, 0, 0, $transparent);
        $black = imagecolorallocate($img, 0x48, 0x48, 0x48);
        imagefilledrectangle($img, 0, 0, 125, 20, $black);
        $white = imagecolorallocate($img, 0xFF, 0xFF, 0xFF);
        imagestring($img, 5, 5, 0, "Poggit Status", $white);

        $y = 0;
        static $colors = [
            BuildResult::LEVEL_LINT => [0x0b, 0xe8, 0xe8],
            BuildResult::LEVEL_WARN => [0xe4, 0xe8, 0x0b],
            BuildResult::LEVEL_ERROR => [0xe0, 0x4d, 0xe2],
            BuildResult::LEVEL_BUILD_ERROR => [0xf2, 0x4d, 0x4d],
            BuildResult::LEVEL_OK => [0x64, 0xFF, 0x00]
        ];
        static $names = [
            BuildResult::LEVEL_LINT => "Lint",
            BuildResult::LEVEL_WARN => "Warning",
            BuildResult::LEVEL_ERROR => "Error",
            BuildResult::LEVEL_BUILD_ERROR => "Build error",
            BuildResult::LEVEL_OK => ""
        ];
        foreach($levels as $level => $count) {
            $y += 20;
            imagefilledrectangle($img, 0, $y, 125, $y + 20, imagecolorallocate($img, ...$colors[$level]));
            $string = "$count " . $names[$level] . ($count > 1 ? "s" : "");
            imagestring($img, 5, 5, $y, $string, $black);
        }

        imagepng($img);
        imagedestroy($img);
    }
}
