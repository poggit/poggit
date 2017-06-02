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
use poggit\ci\cause\V2PullRequestBuildCause;
use poggit\ci\RepoZipball;
use poggit\Poggit;
use poggit\utils\internet\CurlUtils;
use poggit\utils\internet\MysqlUtils;
use poggit\utils\lang\NativeError;

class PullRequestHandler extends WebhookHandler {
    public function handle() {
        Poggit::getLog()->i("Handling pull_request event from GitHub API for repo {$this->data->repository->full_name}");
        $repo = $this->data->repository;
        if($repo->id !== $this->assertRepoId) throw new WebhookException("webhookKey doesn't match sent repository ID", WebhookException::LOG_IN_WARN | WebhookException::OUTPUT_TO_RESPONSE);
        $pr = $this->data->pull_request;
        if($this->data->action !== "opened" and $this->data->action !== "synchronize") { // reopened included in synchronize
            echo "No action needed\n";
            return;
        }

        $repoInfo = MysqlUtils::query("SELECT repos.owner, repos.name, repos.build, users.token FROM repos 
            INNER JOIN users ON users.uid = repos.accessWith
            WHERE repoId = ?", "i", $repo->id)[0] ?? null;
        if($repoInfo === null or 0 === (int) $repoInfo["build"]) throw new WebhookException("Poggit CI not enabled for repo", WebhookException::OUTPUT_TO_RESPONSE);
        if($repoInfo["owner"] !== $repo->owner->login or $repoInfo["name"] !== $repo->name) {
            MysqlUtils::query("UPDATE repos SET owner = ?, name = ? WHERE repoId = ?",
                "ssi", $repo->owner->name, $repo->name, $repo->id);
        }
        WebhookHandler::$token = $token = $repoInfo["token"];

        $branch = $pr->head->ref;
        $zipball = new RepoZipball("repos/{$pr->head->repo->full_name}/zipball/$branch", $token, "repos/{$pr->head->repo->full_name}");
        $manifestFile = ".poggit.yml";
        if(!$zipball->isFile($manifestFile)) {
            $manifestFile = ".poggit/.poggit.yml";
            if(!$zipball->isFile($manifestFile)) throw new WebhookException(".poggit.yml not found", WebhookException::OUTPUT_TO_RESPONSE);
        }
        echo "Using manifest at $manifestFile\n";
        try{
            $manifest = yaml_parse($zipball->getContents($manifestFile));
        }catch(NativeError $e){
            throw new WebhookException("Error parsing $manifestFile: {$e->getMessage()}", WebhookException::OUTPUT_TO_RESPONSE | WebhookException::NOTIFY_AS_COMMENT, $repo->full_name, $pr->head->sha);
        }

        if(!($manifest["pulls"] ?? true)) throw new WebhookException("Poggit CI not enabled for PRs", WebhookException::OUTPUT_TO_RESPONSE);
        if(isset($manifest["branches"]) and !in_array($pr->base->ref, (array) $manifest["branches"])) {
            throw new WebhookException("Poggit CI not enabled for branch", WebhookException::OUTPUT_TO_RESPONSE);
        }

        if($manifest["submodule"] ?? false) {
            $count = Poggit::getSecret("perms.submoduleQuota")[$repo->id] ?? 3;
            $zipball->parseModules($count, $branch);
        }

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
            $project->path = trim($mp["path"] ?? "", "/");
            if(strlen($project->path) > 0) $project->path .= "/";
            $project->type = (int) $row["type"];
            $project->framework = $mp["model"] ?? "default";
            $project->lang = (bool) (int) $row["lang"];
            $project->devBuilds = (int) $row["devBuilds"];
            $project->prBuilds = (int) $row["prBuilds"];
            $project->manifest = $mp;
            $projects[$name] = $project;
        }

        $commits = CurlUtils::ghApiGet("repos/{$repo->full_name}/pulls/{$pr->number}/commits", $token);
        $commitMessages = [];
        foreach($commits as $commit) {
            $commitMessages[] = $commit->commit->message;
        }
        $changedFiles = [];
        $files = CurlUtils::ghApiGet("repos/{$repo->full_name}/pulls/{$pr->number}/files", $token);
        foreach($files as $file) {
            $changedFiles[] = $file->filename;
        }

        $cause = new V2PullRequestBuildCause();
        $cause->repoId = $repo->id;
        $cause->prNumber = $pr->number;
        $cause->commit = $pr->head->sha;

        ProjectBuilder::buildProjects($zipball, $repo, $projects, $commitMessages, $changedFiles, $cause, $this->data->sender->id, function (WebhookProjectModel $project): int {
            return ++$project->prBuilds;
        }, ProjectBuilder::BUILD_CLASS_PR, $branch, $pr->head->sha);
    }
}
