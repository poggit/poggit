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

namespace poggit\release\index;

use poggit\module\Module;
use poggit\utils\internet\MysqlUtils;

class ReleaseListJsonModule extends Module {
    public function getName(): string {
        return "releases.json";
    }

    public function getAllNames(): array {
        return ["releases.json", "plugins.json"];
    }

    public function output() {
        $data = MysqlUtils::query("SELECT 
            releaseId AS id,
            r.name,
            r.version,
            r.shortDesc AS tagline,
            CONCAT('https://poggit.pmmp.io/r/', r.artifact) AS artifact_url,
            art.dlCount AS downloads,
            repos.repoId AS repo_id,
            CONCAT(repos.owner, '/', repos.name) AS repo_name,
            p.projectId AS project_id,
            p.name AS project_name,
            b.buildId AS build_id,
            b.internal AS build_number,
            b.sha AS build_commit,
            CONCAT('https://poggit.pmmp.io/r/', r.description) AS description_url,
            icon AS icon_url,
            IF(changelog = 1, NULL, CONCAT('https://poggit.pmmp.io/r/', r.changelog)) AS changelog_url,
            r.license,
            IF(license = 'custom', CONCAT('https://poggit.pmmp.io/r/', r.licenseRes), NULL) AS license_url,
            (r.flags & 2) > 0 AS is_pre_release,
            (r.flags & 4) > 0 AS is_outdated,
            (r.flags & 8) > 0 AS is_official,
            UNIX_TIMESTAMP(r.creation) AS submission_date,
            r.state,
            r.updateTime AS last_state_change_date,
            (SELECT group_concat(category ORDER BY isMainCategory DESC SEPARATOR ',') FROM release_categories rc WHERE rc.projectId = r.projectId) AS categories,
            (SELECT group_concat(word SEPARATOR ',') FROM release_keywords rw WHERE rw.projectId = r.projectId) AS keywords,
            (SELECT group_concat(CONCAT(since, ',', till) SEPARATOR ';') FROM release_spoons rs WHERE rs.releaseId = r.releaseId) AS api,
            (SELECT group_concat(CONCAT(name, ':', version, ':', depRelId, ':', isHard) SEPARATOR ';') FROM release_deps rd WHERE rd.releaseId = r.releaseId) AS deps
            FROM releases r
                INNER JOIN builds b ON r.buildId = b.buildId
                INNER JOIN projects p ON r.projectId = p.projectId
                INNER JOIN repos ON p.repoId = repos.repoId
                INNER JOIN resources art ON art.resourceId = r.artifact
            "));

        echo json_encode($data, JSON_PRETTY_PRINT | JSON_BIGINT_AS_STRING | JSON_UNESCAPED_SLASHES);
    }
}
