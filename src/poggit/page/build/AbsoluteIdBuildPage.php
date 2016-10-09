<?php

/*
 * pogit
 *
 * Copyright (C) 2016
 */

namespace poggit\page\build;

use poggit\exception\GitHubAPIException;
use poggit\page\Page;
use poggit\Poggit;
use poggit\session\SessionUtils;
use function poggit\redirect;

class AbsoluteIdBuildPage extends Page {
    public function getName() : string {
        return "babs";
    }

    public function output() {
        $id = hexdec($this->getQuery());
        $builds = Poggit::queryAndFetch(
            "SELECT builds.class, builds.internal, projects.repoId, repos.owner, repos.name, projects.name AS pname
            FROM builds INNER JOIN projects ON builds.projectId = projects.projectId
            INNER JOIN repos ON projects.repoId = repos.repoId
            WHERE builds.buildId = ?", "i", $id);
        if(!isset($builds[0])) {
            $this->errorNotFound();
        }
        $build = $builds[0];
        header("Content-Type: text/plain");
        $session = SessionUtils::getInstance();
        try {
            $repo = Poggit::ghApiGet("repositories/" . $build["repoId"], $session->isLoggedIn() ? $session->getLogin()["access_token"] : "");
        } catch(GitHubAPIException $e) {
            return;
        }
        $classes = [
            Poggit::BUILD_CLASS_DEV => "dev",
            Poggit::BUILD_CLASS_BETA => "beta",
            Poggit::BUILD_CLASS_RELEASE => "rc",
        ];
        redirect("build/" . $build["owner"] . "/" . $build["name"] . "/" . $build["pname"] . "/" . $classes[$build["class"]] . ":" . $build["internal"]);
    }
}
