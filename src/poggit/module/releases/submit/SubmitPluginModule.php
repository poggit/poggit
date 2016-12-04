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
        if(count($parts) < 3 or isset($_REQUEST["showRules"])) redirect("help.release.submit");
        if(count($parts) < 4) redirect("ci/$parts[0]/$parts[1]/$parts[2]#releases");
        list($this->owner, $this->repo, $this->project, $this->build) = $parts;
        $this->build = (int) $this->build;

        $session = SessionUtils::getInstance();
        if(!$session->isLoggedIn()) throw new RequireLoginVarPage("Submit a release");

        $projects = Poggit::queryAndFetch("SELECT repos.repoId, projects.type, projects.path FROM projects 
            INNER JOIN repos ON repos.repoId = projects.repoId WHERE repos.owner = ? AND repos.name = ? AND projects.name = ?",
            "sss", $this->owner, $this->repo, $this->project);
        if(count($projects) === 0) $this->errorNotFound();
        $this->projectDetails = $projects[0];
        $this->projectDetails["repoId"] = (int) $this->projectDetails["repoId"];
        $this->projectDetails["type"] = (int) $this->projectDetails["type"];
        if(Poggit::PROJECT_TYPE_PLUGIN !== (int) $this->projectDetails["type"]) $this->errorBadRequest("Only plugins can be released!");

        $lastRelease = Poggit::queryAndFetch("SELECT r.releaseId, r.name, r.shortDesc, r.description FROM releases r
            INNER JOIN projects ON projects.projectId = r.projectId
            INNER JOIN repos ON repos.repoId = projects.repoId
            WHERE repos.owner = ? AND repos.name = ? AND projects.name = ?
            ORDER BY creation DESC LIMIT 1", "sss", $this->owner, $this->repo, $this->project);
        if(count($lastRelease) === 1) {
            $this->action = "update";
            $this->lastRelease = $lastRelease[0];
            $this->lastRelease["description"] = (int) $this->lastRelease["description"];
            $this->lastRelease["releaseId"] = (int) $this->lastRelease["releaseId"];
        } else {
            $this->action = "submit";
        }

        throw new RealSubmitPage($this);
    }
}
