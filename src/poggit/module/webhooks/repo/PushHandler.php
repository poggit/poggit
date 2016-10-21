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

namespace poggit\module\webhooks\repo;

use poggit\builder\cause\V2PushBuildCause;
use poggit\builder\ProjectBuilder;
use poggit\builder\RepoZipball;
use poggit\Poggit;

class PushHandler extends RepoWebhookHandler {
    public $initProjectId, $nextProjectId;

    public function handle() {
        $repo = $this->data->repository;
        if($repo->id !== $this->assertRepoId) throw new StopWebhookExecutionException("webhookKey doesn't match sent repository ID");

        $repoInfo = Poggit::queryAndFetch("SELECT repos.owner, repos.name, repos.build, users.token FROM repos 
            INNER JOIN users ON users.uid = repos.accessWith
            WHERE repoId = ?", "i", $repo->id)[0] ?? null;
        if($repoInfo === null or 0 === (int) $repoInfo["build"]) throw new StopWebhookExecutionException("Poggit Build not enabled for repo");

        $this->initProjectId = $this->nextProjectId = (int) Poggit::queryAndFetch("SELECT IFNULL(MAX(projectId), 0) + 1 AS id FROM projects")[0]["id"];

        if($repoInfo["owner"] !== $repo->owner->name or $repoInfo["name"] !== $repo->name) {
            Poggit::queryAndFetch("UPDATE repos SET owner = ?, name = ? WHERE repoId = ?",
                "ssi", $repo->owner->name, $repo->name, $repo->id);
        }
        RepoWebhookHandler::$token = $repoInfo["token"];

        $branch = self::refToBranch($this->data->ref);
        $zipball = new RepoZipball("repos/$repo->full_name/zipball/$branch", $repoInfo["token"]);
        $manifestFile = ".poggit/.poggit.yml";
        if(!$zipball->isFile($manifestFile)) {
            $manifestFile = ".poggit.yml";
            if(!$zipball->isFile($manifestFile)) throw new StopWebhookExecutionException(".poggit.yml not found");
        }
        echo "Using manifest at $manifestFile\n";
        $manifest = @yaml_parse($zipball->getContents($manifestFile));

        if(isset($manifest["branches"]) and !in_array($branch, (array) $manifest["branches"])) throw new StopWebhookExecutionException("Poggit Build not enabled for branch");

        $projectsBefore = $this->loadDbProjects($repo->id);
        $projectsDeclared = $this->findProjectsFromManifest($manifest);

        /** @var WebhookProjectModel[] $projects */
        $projects = [];
        foreach($projectsDeclared as $project) {
            if(isset($projectsBefore[$project->name])) {
                $before = $projectsBefore[$project->name];
                $project->projectId = (int) $before["projectId"];
                $project->devBuilds = (int) $before["devBuilds"];
                $project->prBuilds = (int) $before["prBuilds"];
                $this->updateProject($project);
            } else {
                $project->projectId = $this->nextProjectId();
                $project->devBuilds = 0;
                $project->prBuilds = 0;
                $this->insertProject($project);
            }
            $projects[$project->projectId] = $project;
        }

        $changedFiles = [];
        foreach($this->data->commits as $commit) {
            foreach(array_merge($commit->added, $commit->removed, $commit->modified) as $file) {
                $changedFiles[$file] = true;
            }
        }
        $cause = new V2PushBuildCause();
        $cause->repoId = $repo->id;
        $cause->commit = $this->data->after;
        ProjectBuilder::buildProjects($zipball, $repo, $projects, array_map(function ($commit) : string {
            return $commit->message;
        }, $this->data->commits), array_keys($changedFiles), $cause, function (WebhookProjectModel $project) {
            return ++$project->devBuilds;
        }, Poggit::BUILD_CLASS_DEV, $branch, $this->data->after);
    }

    /**
     * @param array $manifest
     * @return WebhookProjectModel[]
     */
    private function findProjectsFromManifest(array $manifest) : array {
        $projects = [];
        foreach($manifest["projects"] as $name => $array) {
            $project = new WebhookProjectModel();
            $project->manifest = $array;
            $project->name = $name;
            $project->path = trim($array["path"] ?? "", "/");
            if(strlen($project->path) > 0) $project->path .= "/";
            static $projectTypes = [
                "lib" => Poggit::PROJECT_TYPE_LIBRARY,
                "library" => Poggit::PROJECT_TYPE_LIBRARY,
            ];
            $project->type = $projectTypes[$array["type"] ?? "invalid string"] ?? Poggit::PROJECT_TYPE_PLUGIN;
            $project->framework = $array["model"] ?? "default";
            $project->lang = isset($array["lang"]);
            $projects[$name] = $project;
        }
        return $projects;
    }

    private function nextProjectId() : int {
        return $this->nextProjectId++;
    }

    private function updateProject(WebhookProjectModel $project) {
        Poggit::queryAndFetch("UPDATE projects SET path = ?, type = ?, framework = ?, lang = ? WHERE projectId = ?",
            "sisii", $project->path, $project->type, $project->framework, (int) $project->lang, $project->projectId);
    }

    private function insertProject(WebhookProjectModel $project) {
        Poggit::queryAndFetch("INSERT INTO poggit.projects (projectId, repoId, name, path, type, framework, lang) VALUES 
            (?, ?, ?, ?, ?, ?, ?)", "iissisi", $project->projectId, $this->data->repository->id, $project->name,
            $project->path, $project->type, $project->framework, (int) $project->lang);
    }
}
