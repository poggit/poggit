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

namespace poggit\home;

use poggit\ci\builder\ProjectBuilder;
use poggit\release\Release;
use poggit\utils\internet\Mysql;
use poggit\utils\PocketMineApi;

class SimpleStats {
    public $users;
    public $repos;
    public $pluginProjects;
    public $virionProjects;
    public $pluginBuilds;
    public $virionBuilds;
    public $releases;
    public $compatibleReleases;
    public $pluginDownloads;
    public $visitingIps;

    public function __construct() {
        $rows = Mysql::query("SELECT
    (SELECT COUNT(*) FROM users) users,
    (SELECT COUNT(*) FROM repos WHERE build) repos,
    (SELECT COUNT(*) FROM projects WHERE type = ?) pluginProjects,
    (SELECT COUNT(*) FROM projects WHERE type = ?) virionProjects,
    (SELECT COUNT(*) FROM builds INNER JOIN projects ON builds.projectId = projects.projectId WHERE type = ?) pluginBuilds,
    (SELECT COUNT(*) FROM builds INNER JOIN projects ON builds.projectId = projects.projectId WHERE type = ?) virionBuilds,
    (SELECT COUNT(DISTINCT projectId) FROM releases WHERE state >= ?) releases,
    (SELECT COUNT(DISTINCT releases.projectId) FROM releases
        INNER JOIN release_spoons ON releases.releaseId = release_spoons.releaseId
        INNER JOIN known_spoons since ON release_spoons.since = since.name
        INNER JOIN known_spoons till ON release_spoons.till = till.name
        WHERE releases.state >= ? AND 
            (SELECT id FROM known_spoons WHERE name = ?) BETWEEN since.id AND till.id) compatibleReleases,
    (SELECT SUM(dlCount) FROM resources WHERE src = 'poggit.release.artifact') pluginDownloads,
    (SELECT COUNT(DISTINCT ip) FROM rsr_dl_ips) visitingIps",
            "iiiiiis",
            ProjectBuilder::PROJECT_TYPE_PLUGIN, ProjectBuilder::PROJECT_TYPE_LIBRARY,
            ProjectBuilder::PROJECT_TYPE_PLUGIN, ProjectBuilder::PROJECT_TYPE_LIBRARY,
            Release::STATE_VOTED, Release::STATE_VOTED,
            PocketMineApi::$LATEST_COMPAT);
	$row = is_array($rows) ? $rows[0] : [];
        foreach($row as $col => $val) {
            $this->{$col} = (int) $val;
        }
    }
}
