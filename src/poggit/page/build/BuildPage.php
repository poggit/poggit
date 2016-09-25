<?php

/*
 * Copyright 2016 poggit
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

namespace poggit\page\build;

use poggit\exception\GitHubAPIException;
use poggit\page\Page;
use poggit\Poggit;
use poggit\session\SessionUtils;

class BuildPage extends Page {
    public function getName() : string {
        return "build";
    }

    public function output() {
        $parts = array_filter(explode("/", $this->getQuery()));
        if(count($parts) === 0) {
            $this->displayOwnProjects();
        } else {
            if(!preg_match('/([A-Za-z0-9\-])+/', $parts[0])) {
                $this->errorNotFound();
            }
            if(count($parts) === 1) {
                $this->displayAccount($parts);
            } elseif(count($parts) === 2) {
                $this->displayRepo($parts);
            } else {
                $this->displayProject($parts);
            }
        }
    }

    public function displayOwnProjects() {

    }

    /**
     * @param string[] $parts
     */
    public function displayAccount(array $parts) {
        list($login) = $parts;
        if($login === SessionUtils::getInstance()->getLogin()["name"]) {
            $this->displayOwnProjects();
            return;
        }
        $repos = [];
        try {
            foreach(Poggit::ghApiGet("users/$login/repos") as $repo) {
                $repos[$repo->name] = $repo;
            }
        } catch(GitHubAPIException $e) {
            if($e->getErrorMessage() === "Not Found") {
                $this->errorNotFound();
            } else {
                $this->errorBadRequest("Cannot handle your request due to GitHub API error: " . $e->getErrorMessage());
            }
        }
        $rows = Poggit::queryAndFetch("SELECT r.repoId as repoId, r.name AS repoName, p.name as projectName,
            (SELECT COUNT(*) FROM builds WHERE builds.projectId=p.projectId) AS builds,
            (SELECT COUNT(*) FROM releases WHERE releases.projectId=p.projectId) AS releases
            FROM projects p INNER JOIN repos r ON p.repoId=r.repoId WHERE r.owner = ?", "s", $login);

        $projects = [];
        foreach($rows as $row) {
            $projects[$row["repoName"]][$row["projectName"]] = $row;
        }
        ?>
        <html>
        <head>
            <?php $this->headIncludes() ?>
            <title>Poggit builds for <?= htmlspecialchars($login) ?></title>
        </head>
        <body>
        <?php $this->outputHeader() ?>
        <div id="body">
            <h2>Projects of <?= htmlspecialchars($login) ?></h2>
            <?php foreach($projects as $repoName => $repoProjects) { ?>
                <div class="toggle" data-name="<?= $repoName ?>"
                    <?= count($repoProjects) <= 1 ? 'data-opened="true"' : "" ?>>

                </div>
            <?php } ?>
        </div>
        </body>
        </html>
        <?php
    }

    /**
     * @param string[] $parts
     */
    public function displayRepo(array $parts) {
        list($login, $repo) = $parts;

    }

    /**
     * @param string[] $parts
     */
    public function displayProject(array $parts) {
        list($login, $repo, $proj) = $parts;

    }
}
