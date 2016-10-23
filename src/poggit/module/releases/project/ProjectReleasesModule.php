<?php

/*
 * Poggit
 *
 * Copyright (C) 2016 Poggit
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

namespace poggit\module\releases\project;

use poggit\module\Module;
use poggit\Poggit;
use function poggit\redirect;

class ProjectReleasesModule extends Module {
    private $doStateReplace = false;

    public function getName() : string {
        return "release";
    }

    public function getAllNames() : array {
        return ["release", "rel", "plugin", "p"];
    }

    public function output() {
        $parts = array_filter(explode("/", $this->getQuery()));
        $preReleaseCond = isset($_REQUEST["pre"]) and $_REQUEST["pre"] != "off" ? "(type = 1 OR type = 2)" : "type = 1";
        $stmt = /** @lang MySQL */
            "SELECT r.releaseId, r.name, UNIX_TIMESTAMP(r.creation) AS created,
                r.shortDesc, r.version, r.type, r.artifact, artifact.type AS artifactType, artifact.dlCount AS dlCount, 
                r.description, descr.type AS descrType, r.icon, icon.mimeType AS iconMime, icon.type AS iconType,
                r.changelog, changelog.type AS changeLogType, r.license, r.flags,
                rp.owner AS author, rp.name AS repo, p.path, p.lang AS hasTranslation,
                (SELECT COUNT(*) FROM releases r3 WHERE r3.projectId = r.projectId AND r3.creation < r.creation) AS updates
                FROM releases r LEFT JOIN releases r2 ON (r.projectId = r2.projectId AND r2.creation > r.creation)
                INNER JOIN projects p ON r.projectId = p.projectId
                INNER JOIN repos rp ON p.repoId = rp.repoId
                INNER JOIN resources icon ON r.icon = icon.resourceId
                INNER JOIN resources artifact ON r.artifact = artifact.resourceId
                INNER JOIN resources descr ON r.description = descr.resourceId
                INNER JOIN resources changelog ON r.changelog = changelog.resourceId
                WHERE r2.releaseId IS NULL AND r.name = ? AND $preReleaseCond";
        if(count($parts) === 0) redirect("pi");
        if(count($parts) === 1) {
            $author = null;
            $name = $parts[0];
            $projects = Poggit::queryAndFetch($stmt, "s", $name);
            if(count($projects) === 0) redirect("pi?term=" . urlencode($name));
            if(count($projects) > 1) redirect("plugins/called/" . urlencode($name));
            $release = $projects[0];
        } else {
            assert(count($parts) === 2);
            list($author, $name) = $parts;
            $projects = Poggit::queryAndFetch($stmt, "s", $name);
            if(count($projects) === 0) redirect("pi?author=" . urlencode($author) . "&term=" . urlencode($name));
            if(count($projects) > 1) {
                foreach($projects as $project) {
                    if(strtolower($project["author"]) === strtolower($author)) {
                        $release = $project;
                        break;
                    }
                }
                if(!isset($release)) redirect("pi?author=" . urlencode($author) . "&term=" . urlencode($name));
                $this->doStateReplace = true;
            } else {
                $release = $projects[0];
            }

            /** @var array $release */

        }
    }
}
