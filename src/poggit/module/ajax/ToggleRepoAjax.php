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

use poggit\exception\GitHubAPIException;
use poggit\module\webhooks\repo\NewGitHubRepoWebhookModule;
use poggit\Poggit;
use poggit\session\SessionUtils;

class ToggleRepoAjax extends AjaxModule {
    private $repoId;
    private $enabled;
    private $repoObj;
    private $owner;
    private $repo;
    private $token;

    protected function impl() {
        // read post fields
        if(!isset($_POST["repoId"])) $this->errorBadRequest("Missing post field 'repoId'");
        $repoId = (int) $_POST["repoId"];
        if(!isset($_POST["enabled"])) $this->errorBadRequest("Missing post field 'enabled'");
        $enabled = $_POST["enabled"] === "true" ? 1 : 0;
        $this->repoId = $repoId;
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
        if(!isset($ok)) $this->errorBadRequest("Repo of ID $repoId is not owned by " . $login["name"]);
        /** @var \stdClass $repoObj */
        if(!$repoObj->permissions->admin) $this->errorBadRequest("You must have admin access to the repo to enable Poggit CI for it!");

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
        Poggit::queryAndFetch("INSERT INTO repos (repoId, owner, name, private, build, accessWith, webhookId, webhookKey)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE 
            owner = ?, name = ?, build = ?, webhookId = ?, webhookKey = ?, accessWith = ?",
            "issiiiisssiisi", $repoId, $this->owner, $this->repo, $repoObj->private, $enabled, $login["uid"], $webhookId,
            $webhookKey, $this->owner, $this->repo, $enabled, $webhookId, $webhookKey, $login["uid"]);

        if(isset($_POST["manifestFile"], $_POST["manifestContent"])) {
            $manifestFile = $_POST["manifestFile"];
            $manifestContent = $_POST["manifestContent"];
            $myName = SessionUtils::getInstance()->getLogin()["name"];
            $post = [
                "message" => "Create $manifestFile\r\n" .
                    "Poggit-CI is enabled for this repo by @$myName\r\n" .
                    "Visit the Poggit-CI page for this repo at " . Poggit::getSecret("meta.extPath") . "ci/$repoObj->full_name",
                "content" => base64_encode($manifestContent),
                "branch" => $repoObj->default_branch,
                "committer" => ["name" => Poggit::getSecret("meta.name"), "email" => Poggit::getSecret("meta.email")]
            ];
            try {
                $nowContent = Poggit::ghApiGet("repos/$repoObj->full_name/contents/" . $_POST["manifestFile"], $this->token);
                $method = "PATCH";
                $post["sha"] = $nowContent->sha;
            } catch(GitHubAPIException $e) {
                $method = "PUT";
            }

            Poggit::ghApiCustom("repos/$repoObj->full_name/contents/" . $_POST["manifestFile"], $method, $post, $this->token);
        }

        // response
        echo json_encode([
            "status" => true
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

    public function getName(): string {
        return "ajax.toggleRepo";
    }
}
