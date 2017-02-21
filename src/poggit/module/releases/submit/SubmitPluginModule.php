<?php

/*
 * Poggit
 *
 * Copyright (C) 2016-2017 Poggit
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

use poggit\builder\ProjectBuilder;
use poggit\module\RequireLoginVarPage;
use poggit\module\VarPageModule;
use poggit\utils\internet\CurlUtils;
use poggit\utils\internet\GitHubAPIException;
use poggit\utils\internet\MysqlUtils;
use poggit\utils\SessionUtils;
use poggit\resource\ResourceManager;
use poggit\Poggit;

class SubmitPluginModule extends VarPageModule {
    public $owner;
    public $repo;
    public $project;
    public $build;

    public $projectDetails;
    public $buildInfo;
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
        if(count($parts) < 3 or isset($_REQUEST["showRules"])) Poggit::redirect("help.release.submit");
        if(count($parts) < 4) Poggit::redirect("ci/$parts[0]/$parts[1]/$parts[2]#releases");
        list($this->owner, $this->repo, $this->project, $this->build) = $parts;
        $this->build = (int) $this->build;

        $session = SessionUtils::getInstance();
        if(!$session->isLoggedIn()) throw new RequireLoginVarPage("Submit a release");

        try {
            $repo = CurlUtils::ghApiGet("repos/$this->owner/$this->repo", $session->getAccessToken());
            if((!isset($repo->permissions) or !$repo->permissions->admin) && Poggit::getAdmlv($session->getName()) < Poggit::MODERATOR) $this->errorAccessDenied();
        } catch(GitHubAPIException $e) {
            $this->errorNotFound();
        }

        $projects = MysqlUtils::query("SELECT projects.projectId, repos.repoId, projects.type, projects.path FROM projects 
            INNER JOIN repos ON repos.repoId = projects.repoId WHERE repos.owner = ? AND repos.name = ? AND projects.name = ?",
            "sss", $this->owner, $this->repo, $this->project);
        if(count($projects) === 0) $this->errorNotFound();
        $this->projectDetails = $projects[0];
        $this->projectDetails["repoId"] = (int) $this->projectDetails["repoId"];
        $this->projectDetails["projectId"] = (int) $this->projectDetails["projectId"];
        $this->projectDetails["type"] = (int) $this->projectDetails["type"];
        if(ProjectBuilder::PROJECT_TYPE_PLUGIN !== (int) $this->projectDetails["type"]) $this->errorBadRequest("Only plugins can be released!");

        $builds = MysqlUtils::query("SELECT buildId, created, sha, branch FROM builds WHERE projectId = ? AND class = ? AND internal = ?", "iii", $this->projectDetails["projectId"], ProjectBuilder::BUILD_CLASS_DEV, $this->build);
        if(count($builds) === 0) $this->errorNotFound();
        $build = $builds[0];
        $build["buildId"] = (int) $build["buildId"];
        $build["created"] = (int) $build["created"];
        $statusStats = MysqlUtils::query("SELECT level, COUNT(*) AS cnt FROM builds_statuses WHERE buildId = ? GROUP BY level ASC", "i", $build["buildId"]);
        foreach($statusStats as $row) {
            $build["statusCount"][(int) $row["level"]] = (int) $row["cnt"];
        }
        $build["statusCount"] = $statusStats;
        $this->buildInfo = $build;

        $lastRelease = MysqlUtils::query("SELECT r.releaseId, r.name, r.shortDesc, r.description, r.version, r.state, r.buildId, r.license, r.licenseRes, r.flags, r.changelog FROM releases r
            INNER JOIN projects ON projects.projectId = r.projectId
            INNER JOIN repos ON repos.repoId = projects.repoId
            WHERE repos.owner = ? AND repos.name = ? AND projects.name = ?
            ORDER BY creation DESC LIMIT 1", "sss", $this->owner, $this->repo, $this->project);
        if(count($lastRelease) > 0) {
            $this->action = "update";
            
            $existingVersion = MysqlUtils::query("SELECT r.releaseId, r.name, r.shortDesc, r.description, r.version, r.state, r.buildId, r.license, r.licenseRes, r.flags, r.changelog FROM releases r
            INNER JOIN projects ON projects.projectId = r.projectId
            INNER JOIN repos ON repos.repoId = projects.repoId
            WHERE repos.owner = ? AND repos.name = ? AND projects.name = ? AND r.buildId = ?
            LIMIT 1", "sssi", $this->owner, $this->repo, $this->project, $build["buildId"]);

            if (count($existingVersion) === 1) {
            $this->lastRelease = $existingVersion[0];   
            } else {
            $this->lastRelease = $lastRelease[0];
            }
 
            $this->lastRelease["description"] = (int) $this->lastRelease["description"];
            $descType = MysqlUtils::query("SELECT type FROM resources WHERE resourceId = ? LIMIT 1","i", $this->lastRelease["description"]);
            $this->lastRelease["desctype"] = $descType[0]["type"];
            $this->lastRelease["releaseId"] = (int) $this->lastRelease["releaseId"];
            $this->lastRelease["buildId"] = (int) $this->lastRelease["buildId"];
            // Changelog
            $this->lastRelease["changelog"] = (int) $this->lastRelease["changelog"];
            if($this->lastRelease["changelog"] !== ResourceManager::NULL_RESOURCE) {
                $clTypeRow = MysqlUtils::query("SELECT type FROM resources WHERE resourceId = ? LIMIT 1","i", $this->lastRelease["changelog"]);
                $this->lastRelease["changelogType"] = $clTypeRow[0]["type"];
            } else {
                $this->lastRelease["changelog"] = null;
                $this->lastRelease["changelogType"] = null;
            }
            // Keywords
            $keywordRow = MysqlUtils::query("SELECT word FROM release_keywords WHERE projectId = ?", "i", $this->projectDetails["projectId"]);
            $this->lastRelease["keywords"] = [];
            foreach ($keywordRow as $row) {
                $this->lastRelease["keywords"][] = $row["word"];
            }
            // Categories
            $categoryRow = MysqlUtils::query("SELECT category, isMainCategory FROM release_categories WHERE projectId = ?", "i", $this->projectDetails["projectId"]);
            $this->lastRelease["categories"] = [];
            $this->lastRelease["maincategory"] = 1;
                foreach ($categoryRow as $row) {
                    if ($row["isMainCategory"] == 1) {
                        $this->lastRelease["maincategory"] = (int) $row["category"];
                    } else {
                        $this->lastRelease["categories"][] = (int) $row["category"];
                    }
                }
            // Spoons
            $this->lastRelease["spoons"] = [];
            $spoons = MysqlUtils::query("SELECT since, till FROM release_spoons WHERE releaseId = ?", "i", $this->lastRelease["releaseId"]);
                if (count($spoons) > 0) {
                foreach ($spoons as $row) {
                    $this->lastRelease["spoons"]["since"][] = $row["since"];
                    $this->lastRelease["spoons"]["till"][] = $row["till"];
                }
            }
            //Permissions
            $this->lastRelease["permissions"] = [];
            $perms = MysqlUtils::query("SELECT val FROM release_perms WHERE releaseId = ?", "i", $this->lastRelease["releaseId"]);
                if (count($perms) > 0) {
                foreach ($perms as $row) {
                    $this->lastRelease["permissions"][] = $row["val"];
                }
            }
            // Dependencies
            $this->lastRelease["deps"] = [];
            $deps = MysqlUtils::query("SELECT name, version, depRelId, isHard FROM release_deps WHERE releaseId = ?", "i", $this->lastRelease["releaseId"]);
                if (count($deps) > 0) {
                foreach ($deps as $row) {
                    $this->lastRelease["deps"]["name"][] = $row["name"];
                    $this->lastRelease["deps"]["version"][] = $row["version"];
                    $this->lastRelease["deps"]["depRelId"][] = (int) $row["depRelId"];
                    $this->lastRelease["deps"]["isHard"][] = (int) $row["isHard"];                   
                }
            }
            // Requirements
            $this->lastRelease["reqr"] = [];
            $reqr = MysqlUtils::query("SELECT type, details, isRequire FROM release_reqr WHERE releaseId = ?", "i", $this->lastRelease["releaseId"]);
                if (count($reqr) > 0) {
                foreach ($reqr as $row) {
                    $this->lastRelease["reqr"]["type"][] = $row["type"];
                    $this->lastRelease["reqr"]["details"][] = $row["details"];
                    $this->lastRelease["reqr"]["isRequire"][] = (int) $row["isRequire"];
                }
            }
        } else {
            $this->action = "submit";
        }

        throw new RealSubmitPage($this);

        // TODO: Clean this page to conform to the MVC model properly
    }
}
