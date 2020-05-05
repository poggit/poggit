<?php

/*
 * Poggit
 *
 * Copyright (C) 2016-2018 Poggit
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

use Exception;
use poggit\ci\builder\ProjectBuilder;
use poggit\ci\cause\V2PushBuildCause;
use poggit\ci\RepoZipball;
use poggit\ci\TriggerUser;
use poggit\Config;
use poggit\Meta;
use poggit\utils\internet\Discord;
use poggit\utils\internet\Mysql;
use RuntimeException;
use function implode;
use function in_array;
use function is_array;
use function str_replace;
use function strtolower;
use function urlencode;

class PushHandler extends WebhookHandler {
    public $initProjectId, $nextProjectId;

    public function handle(string &$repoFullName, string &$sha) {
        $repoFullName = $this->data->repository->full_name;
        $sha = $this->data->after;
        Meta::getLog()->i("Handling push event from GitHub API for repo {$this->data->repository->full_name}");
        $repo = $this->data->repository;
        if($repo->id !== $this->assertRepoId) {
            throw new WebhookException("webhookKey doesn't match sent repository ID", WebhookException::LOG_INTERNAL | WebhookException::OUTPUT_TO_RESPONSE);
        }

        if($this->data->head_commit === null) {
            throw new WebhookException("Branch/tag deletion doesn't need handling", WebhookException::OUTPUT_TO_RESPONSE);
        }

        $repoInfo = Mysql::query("SELECT repos.owner, repos.name, repos.build, users.token, users.name uname FROM repos
            INNER JOIN users ON users.uid = repos.accessWith
            WHERE repoId = ?", "i", $repo->id)[0] ?? null;
        if($repoInfo === null or (int) $repoInfo["build"] === 0) {
            throw new WebhookException("Poggit CI not enabled for repo", WebhookException::OUTPUT_TO_RESPONSE);
        }

        $this->initProjectId = $this->nextProjectId = (int) Mysql::query("SELECT IFNULL(MAX(projectId), 0) + 1 AS id FROM projects")[0]["id"];

        if($repoInfo["owner"] !== $repo->owner->name or $repoInfo["name"] !== $repo->name) {
            Mysql::query("UPDATE repos SET owner = ?, name = ? WHERE repoId = ?",
                "ssi", $repo->owner->name, $repo->name, $repo->id);
        }
        WebhookHandler::$token = $repoInfo["token"];
        WebhookHandler::$user = $repoInfo["uname"];

        $branch = self::refToBranch($this->data->ref);
        $zero = 0;
        $zipball = new RepoZipball("repos/$repo->full_name/zipball/" . urlencode($branch), $repoInfo["token"], "repos/$repo->full_name", $zero, null, Meta::getMaxZipballSize($repo->id));

        $manifestFile = ".poggit.yml";
        if(!$zipball->isFile($manifestFile)) {
            $manifestFile = ".poggit/.poggit.yml";
            if(!$zipball->isFile($manifestFile)) {
                throw new WebhookException(".poggit.yml not found", WebhookException::OUTPUT_TO_RESPONSE);
            }
        }
        echo "Using manifest at $manifestFile\n";
        try {
            $manifest = yaml_parse($zipball->getContents($manifestFile));
            if(!is_array($manifest)) {
                throw new RuntimeException("$manifestFile should contain a YAML mapping");
            }
            if(!isset($manifest["projects"])) {
                throw new RuntimeException("$manifestFile does not contain the 'projects' attribute");
            }
        } catch(Exception $e) {
            throw new WebhookException("Error parsing $manifestFile: {$e->getMessage()}", WebhookException::OUTPUT_TO_RESPONSE | WebhookException::NOTIFY_AS_COMMENT, $repo->full_name, $this->data->after);
        }

        if(isset($manifest["branches"]) and !in_array($branch, (array) $manifest["branches"], true)) {
            throw new WebhookException("Poggit CI not enabled for branch", WebhookException::OUTPUT_TO_RESPONSE);
        }

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
                $cnt = (int) Mysql::query("SELECT IFNULL(COUNT(*), 0) cnt FROM builds WHERE triggerUser = ? AND class = ? AND internal = ? AND UNIX_TIMESTAMP() - UNIX_TIMESTAMP(created) < 604800", "iii", $this->data->sender->id, ProjectBuilder::BUILD_CLASS_DEV, 1)[0]["cnt"];
                if($cnt >= ($quota = Meta::getSecret("perms.projectQuota")[$this->data->sender->id] ?? Config::MAX_WEEKLY_PROJECTS)) {
                    $ips = implode(", ", Mysql::getUserIps($this->data->sender->id));
                    Discord::auditHook(<<<MESSAGE
@{$this->data->sender->login} tried to create project {$project->name} in repo {$project->repo[0]}/{$project->repo[1]}, but he is blocked because he created too many projects ($cnt) this week.
MESSAGE
                        , "Throttle audit", [
                            [
                                "title" => $this->data->sender->login,
                                "url" => "https://github.com/" . $this->data->sender->login,
                            ]
                        ]);

                    $discordInvite = Meta::getSecret("discord.serverInvite");
                    throw new WebhookException(<<<MESSAGE
You are trying to create too many projects. We only allow creating up to $quota new projects per week.

Contact SOFe [on Discord]($discordInvite) to request for extra quota. We will increase your quota **for free** if you are really trying to build **your own plugins**, i.e. not forks or copied code.

**Do not try to use another account just to overcome this limit**, or you may be **banned** from Poggit.
MESSAGE
                        , WebhookException::LOG_INTERNAL | WebhookException::OUTPUT_TO_RESPONSE | WebhookException::NOTIFY_AS_COMMENT, $repo->full_name, $this->data->after, true);
                }
                $project->projectId = $this->getNextProjectId();
                $project->devBuilds = 0;
                $project->prBuilds = 0;
                $this->insertProject($project);
            }

            $projects[strtolower($project->name)] = $project;
        }

        $cause = new V2PushBuildCause();
        $cause->repoId = $repo->id;
        $cause->commit = $this->data->after;

        $buildByDefault = $manifest["build-by-default"] ?? true;

        ProjectBuilder::buildProjects($zipball, $repo, $projects, $this->data->commits, $cause, new TriggerUser($this->data->sender), function(WebhookProjectModel $project) {
            return ++$project->devBuilds;
        }, ProjectBuilder::BUILD_CLASS_DEV, $branch, $this->data->after, $buildByDefault);
    }

    /**
     * @param array $manifest
     * @return WebhookProjectModel[]
     * @throws WebhookException
     */
    private function findProjectsFromManifest(array $manifest): array {
        $projects = [];
        static $projectTypes = [
            "lib" => ProjectBuilder::PROJECT_TYPE_LIBRARY,
            "library" => ProjectBuilder::PROJECT_TYPE_LIBRARY,
        ];
        if(!is_array($manifest["projects"])) {
            throw new WebhookException(".poggit.yml does not contain the projects attribute or has an invalid format", WebhookException::OUTPUT_TO_RESPONSE | WebhookException::NOTIFY_AS_COMMENT, $this->data->repository->full_name, $this->data->after);
        }
        foreach($manifest["projects"] as $name => $array) {
            $project = new WebhookProjectModel();
            $project->manifest = $array;
            $project->repo = [$this->data->repository->owner->login, $this->data->repository->name];
            $project->name = str_replace(["/", "#", "?", "&", "\\", "\n", "\r", "<", ">", "\"", "'"], [".", "-", "-", "-", ".", ".", ".", "", "", "", ""], $name);
            if($project->name !== $name) {
                GitHubWebhookModule::addWarning("Sanitized project name, from \"$name\" to \"$project->name\"");
            }
            $project->path = ProjectBuilder::normalizeProjectPath($array["path"] ?? "");
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
