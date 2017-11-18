<?php

/*
 *
 * poggit
 *
 * Copyright (C) 2017 SOFe
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
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
        $row = Mysql::query("SELECT
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
    (SELECT COUNT(DISTINCT ip) FROM user_ips) visitingIps",
            "iiiiiis",
            ProjectBuilder::PROJECT_TYPE_PLUGIN, ProjectBuilder::PROJECT_TYPE_LIBRARY,
            ProjectBuilder::PROJECT_TYPE_PLUGIN, ProjectBuilder::PROJECT_TYPE_LIBRARY,
            Release::STATE_VOTED, Release::STATE_VOTED,
            PocketMineApi::LATEST_COMPAT)[0];
        foreach($row as $col => $val) {
            $this->{$col} = (int) $val;
        }
    }
}
