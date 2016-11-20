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
use poggit\model\ProjectThumbnail;
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
    private $repos;
    private $projects;

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
        $this->repos = $repos;

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
                $method = "PUT";
                $post["sha"] = $nowContent->sha;
            } catch(GitHubAPIException $e) {
                $method = "PUT";
            }

            Poggit::ghApiCustom("repos/$repoObj->full_name/contents/" . $_POST["manifestFile"], $method, $post, $this->token);
        }

        if($this->enabled) {
            $ids = array_map(function ($id) {
                return "p.repoId=$id";
            }, array_keys($this->repos));
            foreach(Poggit::queryAndFetch("SELECT r.repoId AS rid, p.projectId AS pid, p.name AS pname,
                (SELECT COUNT(*) FROM builds WHERE builds.projectId=p.projectId 
                        AND builds.class IS NOT NULL) AS bcnt,
                IFNULL((SELECT CONCAT_WS(',', buildId, internal) FROM builds WHERE builds.projectId = p.projectId
                        AND builds.class = ? ORDER BY created DESC LIMIT 1), 'null') AS bnum
                FROM projects p INNER JOIN repos r ON p.repoId=r.repoId WHERE r.build=1 AND (" .
                implode(" OR ", $ids) . ") ORDER BY r.name, pname", "i", Poggit::BUILD_CLASS_DEV) as $projRow) {
                $project = new ProjectThumbnail();
                $project->id = (int) $projRow["pid"];
                $project->name = $projRow["pname"];
                $project->buildCount = (int) $projRow["bcnt"];
                if($projRow["bnum"] === "null") {
                    $project->latestBuildGlobalId = null;
                    $project->latestBuildInternalId = null;
                } else {
                    list($project->latestBuildGlobalId, $project->latestBuildInternalId) = array_map("intval", explode(",", $projRow["bnum"]));
                }
                $repo = $this->$repos[(int) $projRow["rid"]];
                $project->repo = $repo;
                $this->projects[] = $project;
            }
        }

        // response
        echo json_encode([
            "repoId" => $this->repoId,
            "enabled" => $this->enabled,
            "panelhtml" => ($this->enabled ? $this->displayReposAJAX() : "")//For the AJAX panel refresh,
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

    private function displayReposAJAX(): string {

        $home = Poggit::getRootPath();
        $opened = "false";

        $panelhtml = "<div class='repotoggle' data-name='$this->repo->full_name'"
            . " data-opened='false' id='repo-$this->repo->id'><h2><a href='$home/ci/$this->repo->full_name'></a>"
            . $this->displayUserAJAX($this->$repo->owner)
            . " / " . $this->repo->name . ", "
            . "<a href='https://github.com/"
            . $this->repo->owner->login
            . "/$this->repo->name' target='_blank'>"
            . "<img class='gh-logo' src='" . Poggit::getRootPath() . "res/ghMark.png' width='16'></a>"
            . "</h2></div>";
        foreach($this->projects as $project) {
            $panelhtml = $panelhtml + $this->thumbnailProjectAJAX($project);
        }
        return $panelhtml;
    }

    private function thumbnailProjectAJAX(ProjectThumbnail $project) {

        if($project->latestBuildInternalId !== null or $project->latestBuildGlobalId !== null) {
            $url = "ci/" . $project->repo->full_name . "/" . urlencode($project->name) . "/" . $project->latestBuildInternalId;
            $buildnumbers = $this->showBuildNumbersAJAX($project->latestBuildGlobalId, $project->latestBuildInternalId, $url);
        } else {
            $buildnumbers = "No builds yet";
        }

        $html = "<div class='brief-info' data-project-id='$project->id'><h3>"
            . "<a href='"
            . Poggit::getRootPath() . "ci/$project->repo->full_name/" . urlencode($project->name) . "'>"
            . htmlspecialchars($project->name) . "</a></h3>"
            . "<p class='remark'>Total: $project->buildCount development build"
            . "(" . ($project->buildCount > 1 ? "s" : "") . ")</p>"
            . "<p class='remark'>Last development build: $buildnumbers</p></div>";
        return $html;
    }

    private function displayUserAJAX($owner) {

        if($owner instanceof stdClass) {
            self::displayUser($owner->login);
            return;
        }
        if($owner->avatar_url !== "") {
            $result = "<img src='$owner->avatar_url'"
                . " width='16'> ";
        }
        $result .= $owner->login . " ";
        $result .= $this->ghLinkAJAX("https://github.com/$owner->login");
        return $result;
    }

    private function ghLinkAJAX($url) {
        $markUrl = Poggit::getRootPath() . "res/ghMark.png";
        $result = "<a href='$url' target='_blank'>";
        $result .= "<img class='gh-logo' src='$markUrl' width='16'>";
        $result .= "</a>";
        return $result;
    }

    private function showBuildNumbersAJAX(int $global, int $internal, string $link = "") {
        $result = "";
        if(strlen($link) > 0) {
            $result .= "<a href='" . Poggit::getRootPath() . $link . "'>";
        }
        $result .= "<span style='font-family:Courier New', monospace;'>#$internal (&amp;" . strtoupper(dechex($global)) . ")</span>";
        if(strlen($link) > 0) {
            $result .= "</a>";
        }
        $result .= "<sup class='hover-title' title='#$internal is the internal build number for your project." .
            "&amp;" . strtoupper(dechex($global)) . "is a unique build ID for all Poggit CI builds</sup>";
    }

    public function getName(): string {
        return "ajax.toggleRepo";
    }
}
