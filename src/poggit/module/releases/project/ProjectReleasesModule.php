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
        $preReleaseCond = (isset($_REQUEST["pre"]) and $_REQUEST["pre"] != "off") ? "(r.type = 1 OR r.type = 2)" : "r.type = 1";
        $stmt = /** @lang MySQL */
            "SELECT r.releaseId, r.name, UNIX_TIMESTAMP(r.creation) AS created,
                r.shortDesc, r.version, r.type, r.artifact, artifact.type AS artifactType, artifact.dlCount AS dlCount, 
                r.description, descr.type AS descrType, r.icon, icon.mimeType AS iconMime, icon.type AS iconType,
                r.changelog, changelog.type AS changeLogType, r.license, r.flags,
                rp.owner AS author, rp.name AS repo, p.name AS projectName, p.projectId, p.path, p.lang AS hasTranslation,
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
            if(count($projects) === 0) redirect("pi?term=" . urlencode($name) . "&error=" . urlencode("No plugins called $name"));
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

        }
        /** @var array $release */

        $iconLink = Poggit::getSecret("meta.extPath") . "r/" . $release["icon"];
        $earliestDate = (int) Poggit::queryAndFetch("SELECT MIN(UNIX_TIMESTAMP(creation)) AS created FROM releases WHERE projectId = ?",
            "i", (int) $release["projectId"])[0]["created"];
//        $tags = Poggit::queryAndFetch("SELECT val FROM release_meta WHERE releaseId = ? AND type = ?", "ii", (int) $release["releaseId"], (int)ReleaseConstants::TYPE_CATEGORY);
        ?>
        <html>
        <head
            prefix="og: http://ogp.me/ns# fb: http://ogp.me/ns/fb# object: http://ogp.me/ns/object# article: http://ogp.me/ns/article# profile: http://ogp.me/ns/profile#">
            <title><?= htmlspecialchars($release["name"]) ?></title>
            <meta property="article:published_time" content="<?= date(DATE_ISO8601, $earliestDate) ?>"/>
            <meta property="article:modified_time" content="<?= date(DATE_ISO8601, (int) $release["created"]) ?>"/>
            <meta property="article:author" content="<?= $release["name"] ?>"/>
            <meta property="article:section" content="Plugins"/>
            <!--            --><?php //foreach($tags as $tag) { ?>
            <!--                <meta property="article:tag" content="--><?//= $tag ?><!--"/>-->
            <!--            --><?php //} ?>
            <?php $this->headIncludes($release["name"] . " - Download from Poggit", $release["shortDesc"], "article", "") ?>
            <meta name="twitter:image:src" content="<?= $iconLink ?>">
        </head>
        </html>
        <?php
    }
}
