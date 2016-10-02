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
        // read post fields
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

        // locate repo
        $session = SessionUtils::getInstance();
        $this->token = $session->getLogin()["access_token"];
        $repos = Poggit::ghApiGet("user/repos?per_page=100", $this->token);
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

        // setup webhooks
        $original = Poggit::queryAndFetch("SELECT repoId, webhookId FROM repos WHERE repoId = $repoId OR owner = ? AND name = ?",
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
        if(count($prev) > 0) {
            Poggit::queryAndFetch("DELETE FROM repos WHERE " . implode(" OR ", $prev));
        }
        $before = 0;
        if($hadBefore = count($original) > 0) {
            $before = (int) $original[0]["webhookId"];
        }
        $webhookId = $this->setupWebhooks($before);

        // save changes
        Poggit::queryAndFetch("INSERT INTO repos (repoId, owner, name, private, `$col`, accessWith, webhookId)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE owner = ?, name = ?, `$col` = ?, webhookId = ?, accessWith = ?", "issiiiissiii",
            $repoId, $this->owner, $this->repo, $repoObj->private, $enabled, $session->getLogin()["uid"], $webhookId,
            $this->owner, $this->repo, $enabled, $webhookId, $session->getLogin()["uid"]);

        // init projects
        $created = $enabled ? $this->setupProjects() : false;

        // response
        echo json_encode([
            "status" => true,
            "created" => $created
        ]);
    }

    private function setupWebhooks(int $id = 0) {
        $token = SessionUtils::getInstance()->getLogin()["access_token"];
        if($id !== 0) {
            try {
                $hook = Poggit::ghApiGet("repos/$this->owner/$this->repo/hooks/$id", $token);
                if($hook->config->url === GitHubRepoWebhook::extPath()) {
                    if(!$hook->active) {
                        Poggit::ghApiCustom("repos/$this->owner/$this->repo/hooks/$hook->id", "PATCH", json_encode([
                            "active" => true,
                        ]), $token);
                    }
                    return $hook->id;
                }
            } catch(GitHubAPIException $e) {
            }
        }
        try {
            $hook = Poggit::ghApiPost("repos/$this->owner/$this->repo/hooks", json_encode([
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
        } catch(GitHubAPIException $e) {
            if($e->getErrorMessage() === "Validation failed") {
                Poggit::getLog()->wtf("Webhook setup failed for repo $this->owner/$this->repo due to duplicated config");
            }
            throw $e;
        }
        return $hook->id;
    }

    private function setupProjects() {
        $zipPath = Poggit::getTmpFile(".zip");
        file_put_contents($zipPath, Poggit::ghApiGet("repos/$this->owner/$this->repo/zipball", $this->token, false, true));
        $zip = new \ZipArchive();
        $zip->open($zipPath);
        $files = [];
        for($i = 0; $i < $zip->numFiles; $i++) {
            $path = $zip->getNameIndex($i);
            $path = substr($path, strpos($path, "/") + 1);
            $object = new \stdClass();
            $object->path = $path;
            $object->name = substr($path, strrpos($path, "/") + 1);
            if(substr($object->path, -1) !== "/") {
                $files[$object->path] = $object;
            }
        }

        if(isset($sha)) unset($sha);
        if(isset($files[".poggit/.poggit.yml"])) {
            $manifest = ".poggit/.poggit.yml";
        } elseif(isset($files[".poggit.yml"])) {
            $manifest = ".poggit.yml";
        } else {
            $method = "PUT";
            create_manifest:
            $projects = [];
            foreach($files as $path => $file) {
                if($file->name === "plugin.yml") {
                    $path = substr($path, 0, -strlen($file->name));
                    $projects[$path !== "" ? str_replace("/", ".", rtrim($path, "/")) : $this->repoObj->name] = [
                        "path" => "/" . $path,
                        "model" => "default"
                    ];
                }
            }
            if(count($projects) === 0) {

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
                    "Poggit Build for this repo has been enabled on $extPath by @" . SessionUtils::getInstance()->getLogin()["name"],
                    "This file has been automatically generated. If the generated file can be improved, please submit an issue at https://github.com/poggit/poggit",
                ]),
                "content" => base64_encode(yaml_emit($manifestData, YAML_UTF8_ENCODING, YAML_LN_BREAK)),
                "branch" => $this->repoObj->default_branch,
                "author" => [
                    "name" => "poggit",
                    "email" => Poggit::getSecret("meta.email"),
                ],
                "committer" => [
                    "name" => "poggit",
                    "email" => Poggit::getSecret("meta.email"),
                ],
            ];
            // TODO improve
            if(isset($sha)) $postData["sha"] = $sha;
            $putResponse = Poggit::ghApiCustom("repos/$this->owner/$this->repo/contents/.poggit/.poggit.yml",
                $method, json_encode($postData), $this->token);
            $putFile = $putResponse->content->html_url;
            $putCommit = $putResponse->commit->html_url;
        }

        if(!isset($manifestData)) {
            assert(isset($manifest));
            /** @var string $manifest */
            $content = Poggit::ghApiGet("repos/$this->owner/$this->repo/contents/$manifest", $this->token);
            $manifestData = yaml_parse(base64_decode($content->content));
            if(!is_array($manifestData)) {
                if($manifest === ".poggit/.poggit.yml") {
                    $sha = $content->sha;
                }
                goto create_manifest;
            }
        }
        // manifest available at $manifestData
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
