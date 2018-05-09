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

namespace poggit\ci\ui;

use poggit\ci\builder\ProjectBuilder;
use poggit\Mbd;
use poggit\Meta;
use poggit\module\HtmlModule;
use poggit\utils\internet\Mysql;
use stdClass;
use function dechex;
use function json_encode;

class VirionListModule extends HtmlModule {
    private $limit;

    public function output() {
        /** @noinspection UnnecessaryCastingInspection */
        $this->limit = (int) ($_REQUEST["top"] ?? 10);

        $libs = Mysql::query("SELECT
                repoId, repoOwner, repoName, t2.projectId, projectName, userProjects, userBuilds,
                maxBuildId lastVirionBuild, last_vb.api lastApi, last_build.main antigen,
                UNIX_TIMESTAMP(last_build.created) lastBuildDate,
                IF(LENGTH(last_vb.version) > 0, CONCAT('v', last_vb.version), 'Unknown version') lastVersion
            FROM (SELECT
                    projects.repoId, owner repoOwner, repos.name repoName, projects.projectId, projects.name projectName,
                    IFNULL(t.userProjects, 0) userProjects, IFNULL(t.userBuilds, 0) userBuilds,
                    (SELECT MAX(buildId) FROM builds b WHERE b.projectId = projects.projectId) maxBuildId
                FROM projects INNER JOIN repos ON projects.repoId = repos.repoId
                LEFT JOIN (SELECT
                        virion_project.projectId virionProjectId,
                        COUNT(DISTINCT user_project.projectId) userProjects,
                        COUNT(DISTINCT user_build.buildId) userBuilds
                    FROM virion_usages
                    INNER JOIN builds virion_build ON virion_usages.virionBuild = virion_build.buildId
                        INNER JOIN projects virion_project ON virion_build.projectId = virion_project.projectId
                    INNER JOIN builds user_build ON virion_usages.userBuild = user_build.buildId
                        INNER JOIN projects user_project ON user_build.projectId = user_project.projectId
                    GROUP BY virion_project.projectId
                    ) t ON t.virionProjectId = projects.projectId
                WHERE projects.type = ? AND projects.framework = ? AND NOT repos.private) t2
            LEFT JOIN builds last_build ON last_build.buildId = t2.maxBuildId
            LEFT JOIN virion_builds last_vb ON last_vb.buildId = t2.maxBuildId
            ORDER BY userProjects DESC, userBuilds DESC, lastBuildDate DESC LIMIT $this->limit",
            "is", ProjectBuilder::PROJECT_TYPE_LIBRARY, "virion");
        ?>
      <html>
      <head
          prefix="og: http://ogp.me/ns# fb: http://ogp.me/ns/fb# object: http://ogp.me/ns/object# article: http://ogp.me/ns/article# profile: http://ogp.me/ns/profile#">
          <?php $this->headIncludes("Poggit - Popular Virions", "The most popular virions on Poggit") ?>
        <title>Top <?= $this->limit ?> Popular Virions | Poggit</title>
      </head>
      <body>
      <?php $this->bodyHeader() ?>
      <div id="body">
        <h1>Top <span style="cursor: pointer; border-bottom: dashed;" onclick='window.location =
              "v?top=" + prompt("How many virions to display?", <?= json_encode($this->limit) ?>);'>
                    <?= $this->limit ?></span> Popular Virions</h1>
        <p>Read more about using virions <a href="<?= Meta::root() ?>virion" target="_blank">here</a>.</p>
        <ol>
            <?php
            foreach($libs as $lib) {
                $this->displayLib((object) $lib);
            }
            ?>
        </ol>
      </div>
      <?php $this->bodyFooter() ?>
      <?php $this->flushJsList(); ?>
      </body>
      </html>
        <?php
    }

    private function displayLib(stdClass $lib) {
        ?>
      <li>
        <h3>
          <a href="<?= Meta::root() ?>ci/<?= "$lib->repoOwner/$lib->repoName/$lib->projectName" ?>"><?= $lib->projectName ?></a>
          (<?= $lib->repoOwner ?><?= $lib->repoName !== $lib->projectName ? " / $lib->repoName" : "" ?>)
            <?php Mbd::ghLink("https://github.com/$lib->repoOwner/$lib->repoName") ?>
        </h3>
        <p class="remark">Antigen: <?= $lib->antigen ?? "N/A" ?></p>
        <p class="remark">Used by <?= $lib->userProjects ?> project(s), totally <?= $lib->userBuilds ?> build(s)</p>
        <p class="remark">Last updated: &amp;<?= dechex($lib->lastVirionBuild) ?> <span class="time" data-timestamp="<?= $lib->lastBuildDate ?>"></span>
          (<?= $lib->lastVersion ?>)</p>
      </li>
        <?php
    }
}
