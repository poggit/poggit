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

declare(strict_types=1);

namespace poggit\ci\api;

use poggit\ci\builder\UserFriendlyException;
use poggit\Meta;
use poggit\module\Module;
use poggit\utils\internet\Mysql;
use poggit\utils\lang\Lang;
use stdClass;
use function count;
use function dechex;
use function gmdate;
use function header;
use function http_response_code;
use function implode;

class GetPluginModule extends Module {
    public function output() {
        header("Content-Type: text/plain");
        $args = Lang::explodeNoEmpty("/", $this->getQuery());
        if(count($args) < 3) {
            http_response_code(400);
            echo implode("\r\n", [
                "Format:", /** @lang TEXT */
                "/p.dl/<repoOwner>/<repoName>/<project>",
                "",
                "Optional arguments: build (int)",
                "",
                "Example (specific dev build):",
                "/p.dl/pmmp/DevTools/PocketMine-DevTools?build=30",
                "OR (get latest dev build)",
                "/p.dl/pmmp/DevTools/PocketMine-DevTools",
            ]);
            return;
        }
        $repoOwner = $args[0];
        $repo = $args[1];
        $project = $args[2];
        $build = $_REQUEST["build"] ?? null;
        if($build !== null){
            $build = (int)$build;
            if($build < 1){
                http_response_code(400);
                echo "Build number must be a positive integer";
                return;
            }
        }

        try {
            $build = self::findPlugin($repoOwner, $repo, $project, $build);

            header("X-Poggit-Plugin-BuildId: " . dechex((int)$build->buildId));
            header("X-Poggit-Plugin-BuildNumber: " . $build->buildNumber);
            header("X-Poggit-Plugin-BuildDate: " . $build->created);

            // resource url handles GitHub authorisation if required (private builds etc)
            Meta::redirect("r/$build->resourceId/$project-Dev-$build->buildNumber.phar");
        } catch(UserFriendlyException $e) {
            http_response_code(404);
            echo $e->getMessage();
        }
    }

    public function findPlugin(string $repoOwner, string $repo, string $project, ?int $build = null): stdClass {
        //get project from repo and project
        if($build === null) {
            $rows = Mysql::query("SELECT b.buildId, b.resourceId, b.internal, b.created 
    FROM builds AS b 
    INNER JOIN projects AS p ON b.projectId = p.projectId
    INNER JOIN repos AS r ON p.repoId = r.repoId
    WHERE r.owner = ? AND r.name = ? AND p.name = ? ORDER BY b.buildId DESC LIMIT 1", "sss", $repoOwner, $repo, $project);
        } else {
            $rows = Mysql::query("SELECT b.buildId, b.resourceId, b.internal, b.created
    FROM builds AS b
    INNER JOIN projects AS p ON b.projectId = p.projectId
    INNER JOIN repos AS r ON p.repoId = r.repoId
    WHERE r.owner = ? AND r.name = ? AND p.name = ? AND b.internal = ? LIMIT 1", "sssi", $repoOwner, $repo, $project, $build);
        }

        if(count($rows) !== 1) {
            throw new UserFriendlyException("No plugin build found for '$repoOwner/$repo/$project' - build #".($build??"latest"));
        }

        $data = new stdClass();
        $data->buildId = (int)$rows[0]["buildId"];
        $data->resourceId = (int)$rows[0]["resourceId"];
        $data->buildNumber = (int)$rows[0]["internal"];
        $data->created = $rows[0]["created"];

        return $data;
    }
}