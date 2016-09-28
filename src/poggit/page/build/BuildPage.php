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

use poggit\model\ProjectThumbnail;
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
        $session = SessionUtils::getInstance();
        $token = $session->getLogin()["access_token"];
        $repos = [];
        $ids = [];
        foreach(Poggit::ghApiGet("user/repos", $token) as $repo) {
            $repo->projects = [];
            $repos[$repo->id] = $repo;
            $ids[] = "r.repoId = $repo->id";
        }
        if(count($repos) === 0) {
            $this->displayNoProjects();
            return;
        }
        foreach(Poggit::queryAndFetch("SELECT r.repoId AS rid, p.projectId AS pid, p.name AS pname,
                (SELECT COUNT(*) FROM builds WHERE builds.projectId = p.projectId) AS bcnt,
                (SELECT buildId FROM builds WHERE builds.projectId = p.projectId AND builds.class = ?
                        ORDER BY created DESC LIMIT 1) AS bid
                FROM projects p INNER JOIN repos r ON p.repoId = r.repoId WHERE " .
            implode(" OR ", $ids) . " ORDER BY r.name", "i", Poggit::BUILD_CLASS_DEV) as $project) {
            $object = new ProjectThumbnail();
            $object->id = (int) $project["pid"];
            $object->name = $project["pname"];
            $object->buildCount = $project["bcnt"];
            $object->latestBuildId = $project["bid"];
            $object->repo = $repos[(int) $project["rid"]];
            $object->repo->projects[] = $object;
        }
        ?>
        <html>
        <head>
            <?php $this->headIncludes() ?>
        </head>
        <body>
        <?php $this->outputHeader() ?>
        <div id="body">
            <?php
            $first = true;
            foreach($repos as $repo) {
                if(count($repo->projects) > 0) { ?>
                    <div class="toggle" data-name="<?= $repo->full_name ?>" <?php if($first) {
                        echo 'data-opened="true"';
                        $first = false;
                    } ?>>
                        <h3>
                            <a href="<?= Poggit::getRootPath() ?>build/<?= $repo->owner->login ?>">
                                <?= $repo->owner->login ?>
                            </a> /
                            <a href="<?= Poggit::getRootPath() ?>build/<?= $repo->full_name ?>">
                                <?= $repo->name ?>
                            </a>
                        </h3>
                        <?php foreach($repo->projects as $project) {
                            $this->thumbnailProject($project);
                        } ?>
                    </div>
                <?php }
            } ?>
        </div>
        </body>
        </html>
        <?php
    }

    public function displayNoProjects() {
    }

    /**
     * @param string[] $parts
     */
    public function displayAccount(array $parts) {
//        list($login) = $parts;
//        $repos = [];
//        try {
//            foreach(Poggit::ghApiGet("users/$login/repos") as $repo) {
//                $repos[$repo->name] = $repo;
//            }
//        } catch(GitHubAPIException $e) {
//            if($e->getErrorMessage() === "Not Found") {
//                $this->errorNotFound();
//            } else {
//                $this->errorBadRequest("Cannot handle your request due to GitHub API error: " . $e->getErrorMessage());
//            }
//        }
////        $rows = Poggit::queryAndFetch("SELECT r.repoId as repoId, r.name AS repoName, p.name as projectName,
////            (SELECT COUNT(*) FROM builds WHERE builds.projectId=p.projectId) AS builds,
////            (SELECT COUNT(*) FROM releases WHERE releases.projectId=p.projectId) AS releases
////            FROM projects p INNER JOIN repos r ON p.repoId=r.repoId WHERE r.owner = ?", "s", $login);
//
//        $projects = [];
//        foreach($rows as $row) {
//            $projects[$row["repoName"]][$row["projectName"]] = $row;
//        }
//        ?>
        <!--        <html>-->
        <!--        <head>-->
        <!--            --><?php //$this->headIncludes() ?>
        <!--            <title>Poggit builds for --><?//= htmlspecialchars($login) ?><!--</title>-->
        <!--        </head>-->
        <!--        <body>-->
        <!--        --><?php //$this->outputHeader() ?>
        <!--        <div id="body">-->
        <!--            <h2>Projects of --><?//= htmlspecialchars($login) ?><!--</h2>-->
        <!--            --><?php //foreach($projects as $repoName => $repoProjects) { ?>
        <!--                <div class="toggle" data-name="--><?//= $repoName ?><!--"-->
        <!--                    --><?//= count($repoProjects) <= 1 ? 'data-opened="true"' : "" ?><!-->-->
        <!---->
        <!--                </div>-->
        <!--            --><?php //} ?>
        <!--        </div>-->
        <!--        </body>-->
        <!--        </html>-->
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

    private function thumbnailProject(ProjectThumbnail $project) {
        ?>
        <div class="thumbnail" data-project-id="<?= $project->id ?>">
            <h4>
                <a href="<?= Poggit::getRootPath() ?>build/<?= $project->repo->full_name ?>/<?= urlencode($project->name) ?>">
                    <?= htmlspecialchars($project->name) ?>
                </a>
            </h4>
            <p class="remark">Totally <?= $project->buildCount ?> development
                build<?= $project->buildCount > 1 ? "s" : "" ?></p>
            <p class="remark">
                Last development build:
                <a href="<?= Poggit::getRootPath() ?>build/<?= $project->repo->full_name ?>/<?= urlencode($project->name) ?>/<?= $project->latestBuildId ?>">
                    #<?= $project->latestBuildId ?>
                </a>
            </p>
        </div>
        <?php
    }
}
