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

namespace poggit\ci\ui;

use poggit\ci\builder\ProjectBuilder;
use poggit\Mbd;
use poggit\module\Module;
use poggit\utils\internet\Mysql;

class VirionListModule extends Module {
    public function getName(): string {
        return "v";
    }

    public function output() {
        $libs = Mysql::query("SELECT
                projects.repoId, owner repoOwner, repos.name repoName, projectId, projects.name projectName,
                IFNULL(t.userProjects, 0) userProjects, IFNULL(t.userBuilds, 0) userBuilds,
                (SELECT MAX(created) FROM builds WHERE builds.projectId = projects.projectId) lastBuildDate
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
                ) t ON virionProjectId = projects.projectId
            WHERE projects.type = ? AND projects.framework = ? AND NOT repos.private
            ORDER BY userProjects DESC, userBuilds DESC",
            "is", ProjectBuilder::PROJECT_TYPE_LIBRARY, "virion");
        ?>
        <html>
        <head prefix="og: http://ogp.me/ns# fb: http://ogp.me/ns/fb# object: http://ogp.me/ns/object# article: http://ogp.me/ns/article# profile: http://ogp.me/ns/profile#">
            <?php $this->headIncludes("Poggit - Popular Virions", "The most-used virions on Poggit") ?>
            <title>Popular Virions | Poggit</title>
        </head>
        <body>
        <?php $this->bodyHeader() ?>
        <div id="body">
            <h1>Popular Virions</h1>
            <ol>
                <?php foreach($libs as $lib) { ?>
                    <li>
                        <h3><?= $lib["projectName"] ?> (<?= $lib["repoOwner"] ?>)<?php Mbd::ghLink("https://github.com/{$lib["repoOwner"]}/{$lib["repoName"]}") ?></h3>
                        <p class="remark">Used by <?= $lib["userProjects"] ?> project(s), totally <?= $lib["userBuilds"] ?> build(s)</p>
                    </li>
                <?php } ?>
            </ol>
        </div>
        <?php $this->bodyFooter() ?>
        </body>
        </html>
        <?php
    }
}
