<?php

/*
 * Copyright 2016 poggit
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

namespace poggit\page\ajax;

use poggit\exception\GitHubAPIException;
use poggit\page\AjaxPage;
use poggit\page\webhooks\GitHubRepoWebhook;
use poggit\Poggit;
use poggit\session\SessionUtils;

class ToggleRepoAjax extends AjaxPage {
    private $repoId;
    private $col;
    private $enabled;
    private $repoObj;
    private $owner;
    private $repo;
    private $token;

    protected function impl() {
        if(!isset($_POST["repoId"])) $this->errorBadRequest("Missing post field 'repoId'");
        $repoId = (int) $_POST["repoId"];
        if(!isset($_POST["property"])) $this->errorBadRequest("Missing post field 'property'");
        $property = $_POST["property"];
        if($property === "build") {
            $col = "build";
        } elseif($property === "release") {
            $col = "rel";
        } else {
            $this->errorBadRequest("Unknown property $property");
            die;
        }
        if(!isset($_POST["enabled"])) $this->errorBadRequest("Missing post field 'enabled'");
        $enabled = $_POST["enabled"] === "true" ? 1 : 0;

        $this->repoId = $repoId;
        $this->col = $col;
        $this->enabled = $enabled;

        $session = SessionUtils::getInstance();
        $this->token = $session->getLogin()["access_token"];
        $repos = Poggit::ghApiGet("https://api.github.com/user/repos", $this->token);
        foreach($repos as $repoObj) {
            if($repoObj->id === $repoId) {
                $ok = true;
                break;
            }
        }
        if(!isset($ok)) {
            $this->errorBadRequest("Repo of ID $repoId is not owned by " . $session->getLogin()["name"]);
        }
        /** @var \stdClass $repoObj */
        /** @var \mysqli $db */
        if($repoObj->private and $col === "rel") {
            $this->errorBadRequest("Private repos cannot be released!");
        }
        $this->repoObj = $repoObj;
        $this->owner = $repoObj->owner->login;
        $this->repo = $repoObj->name;

        $original = Poggit::queryAndFetch("SELECT webhookId FROM repos WHERE repoId = $repoId");
        $before = 0;
        if($hadBefore = count($original) > 0) {
            $before = (int) $original[0]["webhookId"];
        }
        $webhookId = $this->setupWebhooks($before);

        Poggit::queryAndFetch("INSERT INTO repos (repoId, owner, name, private, `$col`, accessWith, webhookId) VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE `$col` = ?, webhookId = ?", "issiisiii", $repoId, $this->owner, $this->repo,
            $repoObj->private, $enabled, $session->getLogin()["access_token"], $webhookId, $enabled, $webhookId);

        $created = false;
        if($enabled) {
            $created = $this->setupProjects();
        }

        echo json_encode([
            "status" => true,
            "created" => $created
        ]);
    }

    private function setupWebhooks(int $id = 0) {
        $token = SessionUtils::getInstance()->getLogin()["access_token"];
        if($id !== 0) {
            try {
                $hook = Poggit::ghApiGet("https://api.github.com/repos/$this->owner/$this->repo/hooks/$id", $token);
                if($hook->url === GitHubRepoWebhook::extPath()) {
                    if(!$hook->active) {
                        Poggit::ghApiCustom("https://api.github.com/repos/$this->owner/$this->repo/hooks/$hook->id", "PATCH", json_encode([
                            "active" => true,
                        ]), $token);
                    }
                    return $hook->id;
                }
            } catch(GitHubAPIException $e) {
            }
        }
        $hook = Poggit::ghApiPost("https://api.github.com/repos/$this->owner/$this->repo/hooks", json_encode([
            "name" => "web",
            "config" => [
                "url" => GitHubRepoWebhook::extPath(),
                "content_type" => "json",
                "secret" => Poggit::getSecret("meta.hookSecret"),
                "insecure_ssl" => "1"
            ],
            "events" => [
                "push",
                "pull_request",
                "release",
            ],
            "active" => true
        ]), $token);
        return $hook->id;
    }

    private function setupProjects() {
        $files = Poggit::ghApiGet("https://api.github.com/repos/$this->owner/$this->repo/contents", $this->token);
        $tree = [];
        foreach($files as $file) {
            $tree[$file->path] = $file;
        }

        if(isset($sha)) unset($sha);
        if(isset($tree[".poggit/.poggit.yml"])) {
            $manifest = ".poggit/.poggit.yml";
        } elseif(isset($tree[".poggit.yml"])) {
            $manifest = ".poggit.yml";
        } else {
            $method = "PUT";
            create_manifest:
            $projects = [];
            foreach($tree as $path => $file) {
                if($file->name === "plugin.yml" and $file->type === "file") {
                    $path = substr($path, 0, -strlen($file));
                    $projects[str_replace("/", ".", $path)] = [
                        "path" => "/" . $path,
                        "model" => "default"
                    ];
                }
            }
            $manifestData = [
                "branches" => $this->repoObj->default_branch,
                "projects" => $projects
            ];
            $extPath = Poggit::getSecret("meta.extPath");
            $postData = [
                "path" => ".poggit/.poggit.yml",
                "message" => implode("\r\n", [
                    "Create .poggit/.poggit.yml",
                    "",
                    "Integration for this repo has been enabled on $extPath by @" . SessionUtils::getInstance()->getLogin()["name"],
                    "This file has been automatically generated. If the generated file can be improved, please submit an issue at https://github.com/poggit/poggit",
                ]),
                "content" => base64_encode(yaml_emit($manifestData, YAML_UTF8_ENCODING, YAML_LN_BREAK)),
                "branch" => $this->repoObj->default_branch,
                "author" => [
                    "name" => "poggit",
                    "email" => Poggit::getSecret("meta.email"),
                ],
            ];
            if(isset($sha)) $postData["sha"] = $sha;
            $putResponse = Poggit::ghApiCustom("https://api.github.com/repos/$this->owner/$this->repo/contents/.poggit/.poggit.yml",
                $method, json_encode($postData), $this->token);
            $putFile = $putResponse->content->html_url;
            $putCommit = $putResponse->commit->html_url;
        }

        if(!isset($manifestData)) {
            assert(isset($manifest));
            /** @noinspection PhpUndefinedVariableInspection */
            $content = Poggit::ghApiGet("https://api.github.com/repos/$this->owner/$this->repo/contents/$manifest", $token);
            $manifestData = yaml_parse(base64_decode($content->content));
            if(!is_array($manifestData)) {
                if($manifest === ".poggit/.poggit.yml") {
                    $sha = $content->sha;
                }
                goto create_manifest;
            }
        }

        // TODO create projects using $manifestData

        return !isset($putResponse, $putFile, $putCommit) ? false : [
            "overwritten" => isset($sha),
            "file" => $putFile,
            "commit" => $putCommit
        ];
    }

    public function getName() : string {
        return "toggleRepo";
    }
}
