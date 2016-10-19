<?php

/*
 * pogit
 *
 * Copyright (C) 2016
 */

namespace poggit\module\build;

use poggit\exception\GitHubAPIException;
use poggit\module\Module;
use poggit\Poggit;
use poggit\session\SessionUtils;
use function poggit\redirect;

class AbsoluteBuildIdModule extends Module {
    public function getName() : string {
        return "babs";
    }

    public function output() {
        $id = hexdec($this->getQuery());
        $builds = Poggit::queryAndFetch(
            "SELECT builds.class, builds.internal, projects.repoId, repos.owner, repos.name, projects.name AS pname
            FROM builds INNER JOIN projects ON builds.projectId = projects.projectId
            INNER JOIN repos ON projects.repoId = repos.repoId
            WHERE builds.buildId = ? AND builds.class IS NOT NULL", "i", $id);
        if(!isset($builds[0])) {
            $this->errorNotFound();
        }
        $build = $builds[0];
        $session = SessionUtils::getInstance();
        try {
            $repo = Poggit::ghApiGet("repositories/" . $build["repoId"], $session->getAccessToken());
        } catch(GitHubAPIException $e) {
            $this->errorNotFound();
            return;
        }
        $classes = [
            Poggit::BUILD_CLASS_DEV => "dev",
            Poggit::BUILD_CLASS_BETA => "beta",
            Poggit::BUILD_CLASS_RELEASE => "rc",
            Poggit::BUILD_CLASS_PR => "pr"
        ];
//        echo '<html><head>';$this->headIncludes();echo '</head><body>';$this->bodyHeader();echo '</body></html>';
        redirect("build/" . $repo->full_name . "/" . $build["pname"] . "/" . $classes[$build["class"]] . ":" . $build["internal"]);
    }
}
