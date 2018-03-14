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

use function array_map;
use function array_slice;
use function count;
use poggit\ci\builder\ProjectBuilder;
use poggit\ci\cause\V2PullRequestBuildCause;
use poggit\ci\RepoZipball;
use poggit\ci\TriggerUser;
use poggit\Meta;
use poggit\utils\internet\Curl;
use poggit\utils\internet\Mysql;
use poggit\utils\lang\NativeError;
use const CASE_LOWER;
use function array_change_key_case;
use function in_array;
use stdClass;
use function strtolower;

class PullRequestHandler extends WebhookHandler {
    public function handle(string &$repoFullName, string &$sha) {
        $repoFullName = $this->data->repository->full_name;
        $sha = $this->data->pull_request->head->sha;
        Meta::getLog()->i("Handling pull_request event from GitHub API for repo {$this->data->repository->full_name}");
        $repo = $this->data->repository;
        if($repo->id !== $this->assertRepoId) throw new WebhookException("webhookKey doesn't match sent repository ID", WebhookException::LOG_IN_WARN | WebhookException::OUTPUT_TO_RESPONSE);
        $pr = $this->data->pull_request;
        if($this->data->action !== "opened" and $this->data->action !== "synchronize") { // reopened included in synchronize
            echo "No action needed\n";
            return;
        }

        $repoInfo = Mysql::query("SELECT repos.owner, repos.name, repos.build, users.token, users.name uname FROM repos 
            INNER JOIN users ON users.uid = repos.accessWith
            WHERE repoId = ?", "i", $repo->id)[0] ?? null;
        if($repoInfo === null or (int) $repoInfo["build"] === 0) throw new WebhookException("Poggit CI not enabled for repo", WebhookException::OUTPUT_TO_RESPONSE);
        if($repoInfo["owner"] !== $repo->owner->login or $repoInfo["name"] !== $repo->name) {
            Mysql::query("UPDATE repos SET owner = ?, name = ? WHERE repoId = ?",
                "ssi", $repo->owner->name, $repo->name, $repo->id);
        }
        WebhookHandler::$token = $token = $repoInfo["token"];
        WebhookHandler::$user = $repoInfo["uname"];

        $branch = $pr->head->ref;
        $zero = 0;
        $zipball = new RepoZipball("repos/{$pr->head->repo->full_name}/zipball/$branch", $token, "repos/{$pr->head->repo->full_name}", $zero, null, Meta::getMaxZipballSize($pr->head->repo->id));
        $manifestFile = ".poggit.yml";
        if(!$zipball->isFile($manifestFile)) {
            $manifestFile = ".poggit/.poggit.yml";
            if(!$zipball->isFile($manifestFile)) throw new WebhookException(".poggit.yml not found", WebhookException::OUTPUT_TO_RESPONSE);
        }
        echo "Using manifest at $manifestFile\n";
        try {
            $manifest = yaml_parse($zipball->getContents($manifestFile));
        } catch(NativeError $e) {
            throw new WebhookException("Error parsing $manifestFile: {$e->getMessage()}", WebhookException::OUTPUT_TO_RESPONSE | WebhookException::NOTIFY_AS_COMMENT, $repo->full_name, $pr->head->sha);
        }

        if(!($manifest["pulls"] ?? true)) throw new WebhookException("Poggit CI not enabled for PRs", WebhookException::OUTPUT_TO_RESPONSE);
        if(isset($manifest["branches"]) and !in_array($pr->base->ref, (array) $manifest["branches"], true)) {
            throw new WebhookException("Poggit CI not enabled for branch", WebhookException::OUTPUT_TO_RESPONSE);
        }

        if($manifest["submodule"] ?? false) {
            $count = Meta::getSecret("perms.submoduleQuota")[$repo->id] ?? 3;
            $zipball->parseModules($count, $branch);
        }
        $manifest["projects"] = array_change_key_case($manifest["projects"], CASE_LOWER);

        /** @var WebhookProjectModel[] $projects */
        $projects = [];
        foreach($this->loadDbProjects($repo->id) as $name => $row) {
            if(!isset($manifest["projects"][$name])) {
                echo "Project $name removed?\n";
                continue;
            }
            $mp = $manifest["projects"][$name];
            $project = new WebhookProjectModel();
            $project->repo = [$repo->owner->login, $repo->name];
            $project->projectId = (int) $row["projectId"];
            $project->name = $name;
            $project->path = ProjectBuilder::normalizeProjectPath($mp["path"] ?? "");
            $project->type = (int) $row["type"];
            $project->framework = $mp["model"] ?? "default";
            $project->lang = (bool) (int) $row["lang"];
            $project->devBuilds = (int) $row["devBuilds"];
            $project->prBuilds = (int) $row["prBuilds"];
            $project->manifest = $mp;
            $projects[strtolower($name)] = $project;
        }

        $commits = Curl::ghApiGet("repos/{$repo->full_name}/pulls/{$pr->number}/commits", $token);
        $commits = [array_slice($commits, -1)[0]->commit];
        $commits[0]->added = [];
        $commits[0]->removed = [];
        $commits[0]->modified = [];

        $files = Curl::ghApiGet("repos/{$repo->full_name}/pulls/{$pr->number}/files", $token);
        foreach($files as $file) {
            $commits[0]->{$file->status}[] = $file->filename;
        }

        $cause = new V2PullRequestBuildCause();
        $cause->repoId = $repo->id;
        $cause->prNumber = $pr->number;
        $cause->commit = $pr->head->sha;

        $buildByDefault = true;

        ProjectBuilder::buildProjects($zipball, $repo, $projects, $commits, $cause, new TriggerUser($this->data->sender), function(WebhookProjectModel $project): int {
            return ++$project->prBuilds;
        }, ProjectBuilder::BUILD_CLASS_PR, $branch, $pr->head->sha, $buildByDefault);
    }
}
