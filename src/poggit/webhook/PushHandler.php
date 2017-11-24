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

namespace poggit\webhook;

use poggit\ci\builder\ProjectBuilder;
use poggit\ci\cause\V2PushBuildCause;
use poggit\ci\RepoZipball;
use poggit\Meta;
use poggit\utils\internet\Mysql;
use poggit\utils\lang\NativeError;

class PushHandler extends WebhookHandler {
    public $initProjectId, $nextProjectId;

    public function handle(string &$repoFullName, string &$sha) {
        $repoFullName = $this->data->repository->full_name;
        $sha = $this->data->after;
        Meta::getLog()->i("Handling push event from GitHub API for repo {$this->data->repository->full_name}");
        $repo = $this->data->repository;
        if($repo->id !== $this->assertRepoId) throw new WebhookException("webhookKey doesn't match sent repository ID", WebhookException::LOG_IN_WARN | WebhookException::OUTPUT_TO_RESPONSE);

        if($this->data->head_commit === null) throw new WebhookException("Branch/tag deletion doesn't need handling", WebhookException::OUTPUT_TO_RESPONSE);

        $IS_PMMP = $repo->id === 69691727;

        $repoInfo = Mysql::query("SELECT repos.owner, repos.name, repos.build, users.token, users.name uname FROM repos
            INNER JOIN users ON users.uid = repos.accessWith
            WHERE repoId = ?", "i", $repo->id)[0] ?? null;
        if($repoInfo === null or (int) $repoInfo["build"] === 0) throw new WebhookException("Poggit CI not enabled for repo", WebhookException::OUTPUT_TO_RESPONSE);

        $this->initProjectId = $this->nextProjectId = (int) Mysql::query("SELECT IFNULL(MAX(projectId), 0) + 1 AS id FROM projects")[0]["id"];

        if($repoInfo["owner"] !== $repo->owner->name or $repoInfo["name"] !== $repo->name) {
            Mysql::query("UPDATE repos SET owner = ?, name = ? WHERE repoId = ?",
                "ssi", $repo->owner->name, $repo->name, $repo->id);
        }
        WebhookHandler::$token = $repoInfo["token"];
        WebhookHandler::$user = $repoInfo["uname"];

        $branch = self::refToBranch($this->data->ref);
        $zero = 0;
        $zipball = new RepoZipball("repos/$repo->full_name/zipball/$branch", $repoInfo["token"], "repos/$repo->full_name", $zero, null, Meta::getMaxZipballSize($repo->id));

        if($IS_PMMP) {
            $pmMax = 10;
            $zipball->parseModules($pmMax, $branch);
            $projectModel = new WebhookProjectModel;
            $projectModel->manifest = ["projects" => ["pmmp" => ["type" => "spoon"]]];
            $projectModel->repo = [$this->data->repository->owner->login, $this->data->repository->name];
            $projectModel->name = "PocketMine-MP";
            $projectModel->path = "";
            $projectModel->type = ProjectBuilder::PROJECT_TYPE_SPOON;
            $projectModel->framework = "spoon";
            $projectModel->lang = false;
            $projectModel->projectId = 210;
            $projectModel->devBuilds = $projectModel->prBuilds = 0;
            foreach(Mysql::query("SELECT class, COUNT(*) AS cnt FROM builds WHERE projectId = 210 GROUP BY class") as $row) {
                switch((int) $row["class"]) {
                    case ProjectBuilder::BUILD_CLASS_DEV:
                        $projectModel->devBuilds = (int) $row["cnt"];
                        break;
                    case ProjectBuilder::BUILD_CLASS_PR:
                        $projectModel->prBuilds = (int) $row["cnt"];
                        break;
                }
            }
            $projects = [$projectModel];
        } else {
            $manifestFile = ".poggit.yml";
            if(!$zipball->isFile($manifestFile)) {
                $manifestFile = ".poggit/.poggit.yml";
                if(!$zipball->isFile($manifestFile)) throw new WebhookException(".poggit.yml not found", WebhookException::OUTPUT_TO_RESPONSE);
            }
            echo "Using manifest at $manifestFile\n";
            try {
                $manifest = yaml_parse($zipball->getContents($manifestFile));
            } catch(NativeError $e) {
                throw new WebhookException("Error parsing $manifestFile: {$e->getMessage()}", WebhookException::OUTPUT_TO_RESPONSE | WebhookException::NOTIFY_AS_COMMENT, $repo->full_name, $this->data->after);
            }

            if(isset($manifest["branches"]) and !in_array($branch, (array) $manifest["branches"], true)) throw new WebhookException("Poggit CI not enabled for branch", WebhookException::OUTPUT_TO_RESPONSE);

            if($manifest["submodule"] ?? false) {
                $count = Meta::getSecret("perms.submoduleQuota")[$repo->id] ?? 3;
                $zipball->parseModules($count, $branch);
            }

            $projectsBefore = $this->loadDbProjects($repo->id);
            $projectsDeclared = $this->findProjectsFromManifest($manifest);

            /** @var WebhookProjectModel[] $projects */
            $projects = [];
            foreach($projectsDeclared as $project) {
                if(isset($projectsBefore[strtolower($project->name)])) {
                    $before = $projectsBefore[strtolower($project->name)];
                    if($project->declaredProjectId !== -1) {
                        GitHubWebhookModule::addWarning("Project already renamed, you may delete the projectId line from .poggit.yml now");
                    }
                    $project->projectId = (int) $before["projectId"];
                    $project->devBuilds = (int) $before["devBuilds"];
                    $project->prBuilds = (int) $before["prBuilds"];
                    $this->updateProject($project);
                } elseif($project->declaredProjectId !== -1) { // no project with such name, but the project declares a project ID, so it might be renamed
                    foreach($projectsBefore as $oldName => $loaded) {
                        if($project->declaredProjectId === (int) $loaded["projectId"]) {
                            $project->projectId = (int) $loaded["projectId"];
                            $project->devBuilds = (int) $loaded["devBuilds"];
                            $project->prBuilds = (int) $loaded["prBuilds"];
                            $project->renamed = true;
                            $this->updateProject($project);
                            GitHubWebhookModule::addWarning("Renamed project {$loaded["name"]} to $project->name");
                            break;
                        }
                    }
                    if(!$project->renamed) { // declares projectId but no previous project in this repo with such projectId
                        throw new WebhookException(".poggit.yml explicitly declared projectId as $project->declaredProjectId, but no projects have such projectId", WebhookException::OUTPUT_TO_RESPONSE | WebhookException::NOTIFY_AS_COMMENT, $repo->full_name, $this->data->after);
                    }
                } else { // brand new project
                    $project->projectId = $this->getNextProjectId();
                    $project->devBuilds = 0;
                    $project->prBuilds = 0;
                    $this->insertProject($project);
                }

                $projects[$project->projectId] = $project;
            }
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
        ProjectBuilder::buildProjects($zipball, $repo, $projects, array_map(function($commit): string {
            return $commit->message;
        }, $this->data->commits), array_keys($changedFiles), $cause, $this->data->sender->id, function(WebhookProjectModel $project) {
            return ++$project->devBuilds;
        }, ProjectBuilder::BUILD_CLASS_DEV, $branch, $this->data->after);
    }

    /**
     * @param array $manifest
     * @return WebhookProjectModel[]
     */
    private function findProjectsFromManifest(array $manifest): array {
        $projects = [];
        foreach($manifest["projects"] as $name => $array) {
            $project = new WebhookProjectModel();
            $project->manifest = $array;
            $project->repo = [$this->data->repository->owner->login, $this->data->repository->name];
            $project->name = str_replace(["/", "#", "?", "&", "\\", "\n", "\r", "<", ">", "\"", "'"], [".", "-", "-", "-", ".", ".", ".", "", "", "", ""], $name);
            if($project->name !== $name) GitHubWebhookModule::addWarning("Sanitized project name, from \"$name\" to \"$project->name\"");
            $project->path = ProjectBuilder::normalizeProjectPath($array["path"] ?? "");
            static $projectTypes = [
                "lib" => ProjectBuilder::PROJECT_TYPE_LIBRARY,
                "library" => ProjectBuilder::PROJECT_TYPE_LIBRARY,
            ];
            $project->type = $projectTypes[$array["type"] ?? "invalid string"] ?? ProjectBuilder::PROJECT_TYPE_PLUGIN;
            $project->framework = $array["model"] ?? "default";
            $project->lang = isset($array["lang"]);
            $project->declaredProjectId = $array["projectId"] ?? -1;
            $projects[$project->name] = $project;
        }
        return $projects;
    }

    private function getNextProjectId(): int {
        return $this->nextProjectId++;
    }

    private function updateProject(WebhookProjectModel $project) {
        Mysql::query("UPDATE projects SET name = ?, path = ?, type = ?, framework = ?, lang = ? WHERE projectId = ?",
            "ssisii", $project->name, $project->path, $project->type, $project->framework, (int) $project->lang, $project->projectId);
    }

    private function insertProject(WebhookProjectModel $project) {
        Mysql::query("INSERT INTO projects (projectId, repoId, name, path, type, framework, lang) VALUES 
            (?, ?, ?, ?, ?, ?, ?)", "iissisi", $project->projectId, $this->data->repository->id, $project->name,
            $project->path, $project->type, $project->framework, (int) $project->lang);
    }
}
