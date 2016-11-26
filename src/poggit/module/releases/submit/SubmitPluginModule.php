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

namespace poggit\module\releases\submit;

use poggit\module\RequireLoginVarPage;
use poggit\module\VarPageModule;
use poggit\Poggit;
use poggit\session\SessionUtils;
use function poggit\redirect;

class SubmitPluginModule extends VarPageModule {
    public $owner;
    public $repo;
    public $project;
    public $buildClass;
    public $build;

    public $projectDetails;

    public $action;
    public $lastRelease = [];

    public function getName(): string {
        return "submit";
    }

    public function getAllNames(): array {
        return ["submit", "update"];
    }

    protected function selectPage() {
        $parts = array_filter(explode("/", $this->getQuery(), 5));
        if(count($parts) < 3 or isset($_REQUEST["showRules"])) throw new ReadRulesSubmitPage(false);
        if(count($parts) < 5) redirect("ci/$parts[0]/$parts[1]/$parts[2]#releases");
        list($this->owner, $this->repo, $this->project, $buildClass, $this->build) = $parts;
        $this->buildClass = array_search($buildClass, Poggit::$BUILD_CLASS_IDEN);
        if($this->buildClass === false or !is_numeric($this->build)) $this->errorBadRequest("Syntax: /submit/:owner/:repo/:project/:buildClass/:buildNumber");
        $this->build = (int) $this->build;

        if(!isset($_POST["readRules"]) or $_POST["readRules"] === "off") throw new ReadRulesSubmitPage(true);
        $session = SessionUtils::getInstance();
        if(!$session->isLoggedIn()) throw new RequireLoginVarPage("Submit a release");

        $projects = Poggit::queryAndFetch("SELECT repos.repoId, projects.type, projects.path FROM projects 
            INNER JOIN repos ON repos.repoId = projects.repoId WHERE repos.owner = ? AND repos.name = ? AND projects.name = ?",
            "sss", $this->owner, $this->repo, $this->project);
        if(count($projects) === 0) $this->errorNotFound();
        $this->projectDetails = $projects[0];
        if(Poggit::PROJECT_TYPE_PLUGIN !== (int) $this->projectDetails["type"]) $this->errorBadRequest("Only plugins can be released!");

        $lastRelease = Poggit::queryAndFetch("SELECT releases.* FROM releases
            INNER JOIN projects ON projects.projectId = releases.projectId
            INNER JOIN repos ON repos.repoId = projects.repoId
            WHERE repos.owner = ? AND repos.name = ? AND projects.name = ?
            ORDER BY creation DESC LIMIT 1", "sss", $this->owner, $this->repo, $this->project);
        if(count($lastRelease) === 1) {
            $this->action = "update";
            Poggit::getLog()->d(json_encode($lastRelease));
            $this->lastRelease = $lastRelease[0];
        } else {
            $this->action = "submit";
        }

        throw new RealSubmitPage($this);
    }
}
