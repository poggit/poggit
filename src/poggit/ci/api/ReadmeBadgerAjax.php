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
use poggit\Meta;
use poggit\module\AjaxModule;
use poggit\utils\internet\GitHub;
use poggit\utils\internet\GitHubAPIException;
use poggit\utils\internet\Mysql;
use function array_map;
use function base64_decode;
use function count;
use function explode;
use function http_response_code;
use function implode;
use function json_encode;
use function preg_match;
use function preg_quote;
use function urlencode;

/**
 * <h1>Insufficiently-tested</h1>
 */
class ReadmeBadgerAjax extends AjaxModule {
    protected function impl() {
        $repoId = (int) $_REQUEST["repoId"];
        try {
            $repo = GitHub::ghApiGet("repositories/$repoId", $token = Session::getInstance()->getAccessToken());
        } catch(GitHubAPIException $e) {
            echo json_encode(["status" => false, "problem" => "Repo not found"]);
            return;
        }
        $projects = array_map(function($row) {
            return $row["name"];
        }, Mysql::query("SELECT name FROM projects WHERE repoId = ?", "i", $repoId));
        if(count($projects) === 0) {
            echo json_encode(["status" => false, "problem" => "No projects to badge"]);
            return;
        }
        try {
            $data = GitHub::ghApiGet("repositories/$repoId/contents/README.md", $token);
        } catch(GitHubAPIException $e) {
            http_response_code(204);
            echo json_encode(["status" => false, "problem" => "No README to badge"]);
            return;
        }
        $readme = explode("\n", base64_decode($data->content));
        foreach($readme as $i => &$line) {
            foreach($projects as $project) {
                $shieldUrl = Meta::getSecret("meta.extPath") . "ci.shield/{$repo->full_name}/" . urlencode($project);
                $ciUrl = Meta::getSecret("meta.extPath") . "ci/{$repo->full_name}/$project";
                $badgeMd = "[![Poggit-CI]($shieldUrl)]($ciUrl)";
                if(preg_match('%^[ \t]*[#]+[ \t]*(\*_){0,2}' . preg_quote($project, '%') . '[^A-Za-z0-9]', $line)) {
                    $line .= " " . $badgeMd;
                }
            }
        }
        unset($line);
        echo json_encode(["status" => true, "type" => "md", "body" => implode("\n", $readme)]);
    }
}
