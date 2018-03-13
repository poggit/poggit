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

namespace poggit\webhook;

use poggit\ci\builder\ProjectBuilder;
use poggit\utils\internet\Mysql;
use poggit\utils\lang\Lang;
use RuntimeException;
use function strtolower;
use function substr;

abstract class WebhookHandler {
    public static $token;
    public static $user;

    public $data;
    /** @var int */
    public $assertRepoId;

    public abstract function handle(string &$repoFullName, string &$sha);

    public static function refToBranch(string $ref): string {
        if(Lang::startsWith($ref, "refs/heads/")) return substr($ref, 11);
        if(Lang::startsWith($ref, "refs/tags/")) return substr($ref, 10);
        throw new RuntimeException("Unknown ref");
    }

    /**
     * @param int $repoId
     * @return array[]
     */
    protected function loadDbProjects(int $repoId): array {
        $rows = Mysql::query("SELECT projectId, name, type, lang, 
            (SELECT IFNULL(MAX(internal), 0) FROM builds WHERE builds.projectId = projects.projectId AND class = ?) AS devBuilds,
            (SELECT IFNULL(MAX(internal), 0) FROM builds WHERE builds.projectId = projects.projectId AND class = ?) AS prBuilds
            FROM projects WHERE repoId = ?", "iii", ProjectBuilder::BUILD_CLASS_DEV, ProjectBuilder::BUILD_CLASS_PR, $repoId);
        $projects = [];
        foreach($rows as $row) {
            $projects[strtolower($row["name"])] = $row;
        }
        return $projects;
    }
}
