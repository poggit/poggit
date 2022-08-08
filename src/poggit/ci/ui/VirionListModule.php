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
        if($this->limit <= 0) $this->limit = 10;

        $libs = [];
        $buildIds = [];
        foreach(Mysql::query("SELECT
                repos.owner repoOwner, repos.name repoName,
                virionProject projectId, projects.name projectName,
                count userProjects, MAX(builds.buildId) buildId
            FROM (SELECT
                    virionProject, COUNT(*) count, SUM(1 / LOG(sinceLastUse / 86400 / 30 + 1)) score
                FROM recent_virion_usages
                GROUP BY virionProject
                ORDER BY score DESC
                LIMIT {$this->limit}
            ) t
            INNER JOIN projects ON projects.projectId = virionProject
            INNER JOIN builds ON projects.projectId = builds.projectId
            INNER JOIN repos ON repos.repoId = projects.repoId
            GROUP BY virionProject ORDER BY score DESC") as $lib) {
            $libs[$lib["buildId"]] = $lib;
            $buildIds[] = $lib["buildId"];
        }

        if(!empty($buildIds)){
            foreach(Mysql::arrayQuery("SELECT buildId, api, version FROM virion_builds WHERE buildId IN (%s)", ["i", $buildIds]) as $build) {
                $libs[$build["buildId"]]["lastApi"] = $build["api"];
                $libs[$build["buildId"]]["lastVersion"] = $build["version"] ?: "Unknown version";
            }
            foreach(Mysql::arrayQuery("SELECT buildId, main, UNIX_TIMESTAMP(created) lastUpdated FROM builds WHERE buildId IN (%s)", ["i", $buildIds]) as $build) {
                $libs[$build["buildId"]]["antigen"] = $build["main"];
                $libs[$build["buildId"]]["lastBuildDate"] = $build["lastUpdated"];
            }
        }
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
        <p class="remark"><?= $lib->antigen ?? "N/A" ?>, Used by <?= $lib->userProjects ?> project(s)</p>
        <p class="remark">Last updated: &amp;<?= dechex((int)$lib->buildId) ?>
          <span class="time" data-timestamp="<?= $lib->lastBuildDate ?>"></span>
          (<?= $lib->lastVersion ?? "null" ?>)</p>
      </li>
        <?php
    }
}
