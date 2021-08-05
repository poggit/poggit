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

use poggit\account\Session;
use poggit\ci\builder\ProjectBuilder;
use poggit\ci\lint\V2BuildStatus;
use poggit\module\AjaxModule;
use poggit\resource\ResourceManager;
use poggit\utils\internet\GitHub;
use poggit\utils\internet\Mysql;
use poggit\utils\OutputManager;
use function array_map;
use function count;
use function dechex;
use function explode;
use function filesize;
use function header;
use function json_decode;
use function json_encode;

class BuildDataRequestAjax extends AjaxModule {
    protected function impl() {
        header("Content-Type: application/json");
        $projectId = (int) $this->param("projectId");
        $class = $this->param("class");
        $internal = $this->param("internal");

        $isPr = $class === ProjectBuilder::BUILD_CLASS_PR;
        $branchColumn = $isPr ? "substring_index(substring_index(cause, '\"prNumber\":',-1), ',', 1)" : "branch";
        $rows = array_map(function(array $input) use ($isPr) {
            $row = (object) $input;
            $row->repoId = (int) $row->repoId;
            $row->cause = json_decode($row->cause);
            $row->date = (int) $row->date;
            if($isPr) $row->branch = (int) $row->branch;
            $row->virions = [];
            $row->lintCount = (int) ($row->lintCount ?? 0);
            $row->worstLint = (int) ($row->worstLint ?? 0);
            if($row->libs !== null) {
                foreach(explode(",", $row->libs ?? "") as $lib) {
                    list($virionName, $virionVersion, $virionBranch, $virionSha, $virionBabs) = explode(":", $lib, 5);
                    $row->virions[$virionName] = [
                        "version" => $virionVersion,
                        "branch" => $virionBranch,
                        "sha" => $virionSha,
                        "babs" => dechex((int)$virionBabs),
                    ];
                }
            }
            $row->dlSize = $row->resourceId === ResourceManager::NULL_RESOURCE ? 0.0 : filesize(ResourceManager::pathTo($row->resourceId, "phar"));
            unset($row->libs);
            return $row;
        }, Mysql::query("SELECT (SELECT repoId FROM projects WHERE projects.projectId = builds.projectId) repoId, cause,
                builds.buildId, class, internal, UNIX_TIMESTAMP(created) date, resourceId, $branchColumn branch, sha, main, path,
                bs.cnt lintCount, bs.maxLevel worstLint,
                virion_builds.version virionVersion, virion_builds.api virionApi,
                (SELECT GROUP_CONCAT(CONCAT_WS(':', vp.name, vvb.version, vb.branch, vb.sha, vb.buildId) SEPARATOR ',') FROM virion_usages
                    INNER JOIN builds vb ON virion_usages.virionBuild = vb.buildId
                    INNER JOIN virion_builds vvb ON vvb.buildId = vb.buildId
                    INNER JOIN projects vp ON vp.projectId = vb.projectId
                    WHERE virion_usages.userBuild = builds.buildId) libs
            FROM builds
                LEFT JOIN (SELECT buildId, COUNT(*) cnt, MAX(level) maxLevel FROM builds_statuses GROUP BY buildId) bs
                    ON bs.buildId = builds.buildId
                LEFT JOIN virion_builds ON builds.buildId = virion_builds.buildId
            WHERE projectId = ? AND class = ? AND internal = ?", "iii", $projectId, $class, $internal));

        if(count($rows) === 0) $this->errorNotFound(true);
        $build = $rows[0];
        if(!GitHub::testPermission($build->repoId, Session::getInstance()->getAccessToken(true), Session::getInstance()->getName(), "pull")) {
            $this->errorNotFound(true);
        }
        $build->statuses = array_map(function(array $row) {
            $om = OutputManager::$tail->startChild();
            $status = V2BuildStatus::unserializeNew(json_decode($row["body"]), $row["class"], (int) $row["level"]);
            $status->echoHtml();
            return ["level" => $status->level, "class" => $row["class"], "html" => $om->terminateGet()];
        }, Mysql::query("SELECT level, class, body FROM builds_statuses WHERE buildId = ?", "i", $build->buildId));
        echo json_encode($build);
    }

    protected function needLogin(): bool {
        return false;
    }
}
