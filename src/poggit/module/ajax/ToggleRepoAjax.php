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

namespace poggit\module\ajax;

use poggit\builder\RepoZipball;
use poggit\exception\GitHubAPIException;
use poggit\module\webhooks\repo\NewGitHubRepoWebhookModule;
use poggit\Poggit;
use poggit\session\SessionUtils;

class ToggleRepoAjax extends AjaxModule {
    private $repoId;
    private $col;
    private $enabled;
    private $repoObj;
    private $owner;
    private $repo;
    private $token;

    protected function impl() {
        // read post fields
        if(!isset($_POST["repoId"])) $this->errorBadRequest("Missing post field 'repoId'");
        $repoId = (int) $_POST["repoId"];
        if(!isset($_POST["property"])) $this->errorBadRequest("Missing post field 'property'");
        $property = $_POST["property"];
        if($property === "build") {
            $col = "build";
//        } elseif($property === "release") {
//            $col = "rel";
        } else {
            $this->errorBadRequest("Unknown property $property");
            die;
        }
        if(!isset($_POST["enabled"])) $this->errorBadRequest("Missing post field 'enabled'");
        $enabled = $_POST["enabled"] === "true" ? 1 : 0;
        $this->repoId = $repoId;
        $this->col = $col;
        $this->enabled = $enabled;

        // locate repo
        $session = SessionUtils::getInstance();
        $login = $session->getLogin();
        $this->token = $session->getAccessToken();
        $repos = Poggit::ghApiGet("user/repos?per_page=50", $this->token); // TODO fix
        foreach($repos as $repoObj) {
            if($repoObj->id === $repoId) {
                $ok = true;
                break;
            }
        }
        if(!isset($ok)) {
            $this->errorBadRequest("Repo of ID $repoId is not owned by " . $login["name"]);
        }
        /** @var \stdClass $repoObj */
        if(!$repoObj->permissions->admin) {
            $this->errorBadRequest("You must have admin access to the repo to enable Poggit CI for it!");
        }
//        if($repoObj->private and $col === "rel") {
//            $this->errorBadRequest("Private repos cannot be released!");
//        }
        $this->repoObj = $repoObj;
        $this->owner = $repoObj->owner->login;
        $this->repo = $repoObj->name;

        // setup webhooks
        $original = Poggit::queryAndFetch("SELECT repoId, webhookId, webhookKey FROM repos WHERE repoId = $repoId OR owner = ? AND name = ?",
            "ss", $this->owner, $this->repo);
        $prev = [];
        foreach($original as $k => $row) {
            if(((int) $row["repoId"]) !== $repoId) { // old repo, should have been deleted,
                $prev[] = "repoId = " . $row["repoId"];
                unset($original[$k]);
            }
        }
        // warning: the `owner` and `name` field may be different from those in $this->repoObj if renamed
        $original = array_values($original);
        if(count($prev) > 0) Poggit::queryAndFetch("DELETE FROM repos WHERE " . implode(" OR ", $prev));
        $beforeId = 0;
        $beforeKey = "invalid string";
        if($hadBefore = count($original) > 0 and is_string($original[0]["webhookKey"])) {
            $beforeId = (int) $original[0]["webhookId"];
            $beforeKey = $original[0]["webhookKey"];
        }
        list($webhookId, $webhookKey) = $this->setupWebhooks($beforeId, $beforeKey);

        // save changes
        Poggit::queryAndFetch("INSERT INTO repos (repoId, owner, name, private, `$col`, accessWith, webhookId, webhookKey)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE 
            owner = ?, name = ?, `$col` = ?, webhookId = ?, webhookKey = ?, accessWith = ?", "issiiiisssiisi",
            $repoId, $this->owner, $this->repo, $repoObj->private, $enabled, $login["uid"], $webhookId, $webhookKey,
            $this->owner, $this->repo, $enabled, $webhookId, $webhookKey, $login["uid"]);

        // init projects
        $created = $enabled ? $this->setupProjects() : false;

        // response
        echo json_encode([
            "status" => true,
            "created" => $created
        ]);
    }

    private function setupWebhooks(int $id, string $webhookKey) {
        $token = $this->token;
        if($id !== 0) {
            try {
                $hook = Poggit::ghApiGet("repos/$this->owner/$this->repo/hooks/$id", $token);
                if($hook->config->url === NewGitHubRepoWebhookModule::extPath() . "/" . bin2hex($webhookKey)) {
                    if(!$hook->active) {
                        Poggit::ghApiCustom("repos/$this->owner/$this->repo/hooks/$hook->id", "PATCH", [
                            "active" => true,
                        ], $token);
                    }
                    return [$hook->id, $webhookKey];
                }
            } catch(GitHubAPIException $e) {
            }
        }
        try {
            $webhookKey = openssl_random_pseudo_bytes(8);
            $hook = Poggit::ghApiPost("repos/$this->owner/$this->repo/hooks", [
                "name" => "web",
                "config" => [
                    "url" => NewGitHubRepoWebhookModule::extPath() . "/" . bin2hex($webhookKey),
                    "content_type" => "json",
                    "secret" => Poggit::getSecret("meta.hookSecret") . bin2hex($webhookKey),
                    "insecure_ssl" => "1"
                ],
                "events" => [
                    "push",
                    "pull_request",
//                    "release",
                    "repository"
                ],
                "active" => true
            ], $token);
            return [$hook->id, $webhookKey];
        } catch(GitHubAPIException $e) {
            if($e->getErrorMessage() === "Validation failed") {
                Poggit::getLog()->wtf("Webhook setup failed for repo $this->owner/$this->repo due to duplicated config");
            }
            throw $e;
        }
    }

    private function setupProjects() {
        $zipball = new RepoZipball("repos/$this->owner/$this->repo/zipball", $this->token);

        if(!$zipball->isFile($manifestName = ".poggit/.poggit.yml")) {
            if(!$zipball->isFile($manifestName = ".poggit.yml")) {
                create_manifest:
                // scan for projects
                $projects = [];
                foreach($zipball->callbackIterator() as $path => $getCont) {
                    if($path === "plugin.yml" or Poggit::endsWith($path, "/plugin.yml")) {
                        $dir = substr($path, 0, -strlen("plugin.yml"));
                        $name = $dir !== "" ? str_replace("/", ".", rtrim($path, "/")) : $this->repo;
                        $object = [
                            "path" => $dir,
                            "model" => "default",
                        ];
                        $projects[$name] = $object;
                    }
                }

                $manifestData = [
                    "branches" => $this->repoObj->default_branch,
                    "projects" => $projects
                ];
                $postData = [
                    "message" => implode("\r\n", ["Created .poggit/.poggit.yml", "",
                        "Poggit CI has been enabled for this repo by @" . SessionUtils::getInstance()->getLogin()["name"],
                        "Visit the Poggit CI page at " . Poggit::getSecret("meta.extPath") . "b/$this->owner/$this->repo",
                        "If the .poggit.yml generator needs improvement, please submit an issue at https://github.com/poggit/poggit/issues"
                    ]),
                    "content" => base64_encode(yaml_emit($manifestData, YAML_UTF8_ENCODING, YAML_LN_BREAK)),
                    "branch" => $this->repoObj->default_branch,
                    "committer" => ["name" => Poggit::getSecret("meta.name"), "email" => Poggit::getSecret("meta.email")]
                ];
                // TODO: Ask the client first! This is so barbaric!
                if(isset($sha)) {
                    $postData["sha"] = $sha;
                    $method = "PATCH";
                } else $method = "PUT";
                $putResponse = Poggit::ghApiCustom("repos/$this->owner/$this->repo/contents/.poggit/.poggit.yml", $method, $postData, $this->token);
                $putFile = $putResponse->content->html_url;
                $putCommit = $putResponse->commit->html_url;
            }
        }

//        if(!isset($manifestData)) {
//            $content = Poggit::ghApiGet("repos/$this->owner/$this->repo/contents/$manifestName", $this->token);
//            $manifestData = @yaml_parse(base64_decode($content->content));
//            if(!is_array($manifestData)) {
//                if($manifestName === ".poggit/.poggit.yml") {
//                    $sha = $content->sha;
//                }
//                goto create_manifest;
//            }
//        }

        // manifest available at $manifestData
        return !isset($putResponse, $putFile, $putCommit) ? false : [
            "overwritten" => isset($sha),
            "file" => $putFile,
            "commit" => $putCommit
        ];
    }

    public function getName() : string {
        return "ajax.toggleRepo";
    }
}
