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

namespace poggit\ci\api;

use poggit\account\RequireLoginException;
use poggit\account\Session;
use poggit\ci\builder\ProjectBuilder;
use poggit\ci\ui\ProjectThumbnail;
use poggit\Meta;
use poggit\module\AjaxModule;
use poggit\utils\internet\GitHub;
use poggit\utils\internet\GitHubAPIException;
use poggit\utils\internet\Mysql;
use poggit\webhook\GitHubWebhookModule;
use stdClass;
use function array_keys;
use function array_map;
use function array_values;
use function base64_encode;
use function bin2hex;
use function count;
use function dechex;
use function explode;
use function htmlspecialchars;
use function implode;
use function is_string;
use function json_encode;
use function random_bytes;
use function strlen;
use function strtoupper;
use function urlencode;

class ToggleRepoAjax extends AjaxModule {
    private $repoId;
    private $enabled;
    private $repoObj;
    private $owner;
    private $repoName;
    private $token;
    private $projectsCount;

    protected function impl() {
        // read post fields
        $repoId = (int) $this->param("repoId", $_POST);
        $enabled = $this->param("enabled", $_POST) === "true" ? 1 : 0;
        $this->repoId = $repoId;
        $this->enabled = $enabled;

        // locate repo
        $session = Session::getInstance();
        $this->token = $session->getAccessToken();
        $repoRaw = GitHub::ghApiGet("repositories/$this->repoId", $this->token);

        if(!($repoRaw->id === $repoId)) $this->errorBadRequest("Repo of ID $repoId is not owned by " . $session->getName());
        /** @var stdClass $repoObj */
        if(!$repoRaw->permissions->admin) $this->errorBadRequest("You must have admin access to the repo to enable Poggit CI for it!");

        $this->repoObj = $repoRaw;
        $rawRepos = [];

//      if(!$validate($repo)) continue;
        $repoRaw->projects = [];
        $rawRepos[$repoRaw->id] = $repoRaw;

        $this->owner = $this->repoObj->owner->login;
        $this->repoName = $this->repoObj->name;

        // setup webhooks
        $original = Mysql::query("SELECT repoId, webhookId, webhookKey FROM repos WHERE repoId = $repoId OR owner = ? AND name = ?",
            "ss", $this->owner, $this->repoName);
        $prev = [];
        foreach($original as $k => $row) {
            if(((int) $row["repoId"]) !== $repoId) { // old repo, should have been deleted,
                $prev[] = "repoId = " . $row["repoId"];
                unset($original[$k]);
            }
        }
        // warning: the `owner` and `name` field may be different from those in $this->repoObj if renamed
        $original = array_values($original);
        if(count($prev) > 0) Mysql::query("DELETE FROM repos WHERE " . implode(" OR ", $prev));
        $beforeId = 0;
        $beforeKey = "invalid string";
        if($hadBefore = count($original) > 0 and is_string($original[0]["webhookKey"])) {
            $beforeId = (int) $original[0]["webhookId"];
            $beforeKey = $original[0]["webhookKey"];
        }
        try {
            list($webhookId, $webhookKey) = $this->setupWebhooks($beforeId, $beforeKey);
        } catch(RequireLoginException $e) {
            echo json_encode([
                "success" => false,
                "message" => $e->getMessage()
            ]);
            return;
        }

        // save changes
        Mysql::query("INSERT INTO repos (repoId, owner, name, private, build, fork, accessWith, webhookId, webhookKey)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE 
            owner = ?, name = ?, private = ?, build = ?, fork = ?, webhookId = ?, webhookKey = ?, accessWith = ?",
            "issiiiiisssiiiisi", $repoId, $this->owner, $this->repoName, $this->repoObj->private, $enabled, $this->repoObj->fork, $session->getUid(), $webhookId,
            $webhookKey, $this->owner, $this->repoName, $this->repoObj->private, $enabled, $this->repoObj->fork, $webhookId, $webhookKey, $session->getUid());
        if($this->enabled) {
            $ids = array_map(function($id) {
                return "p.repoId=$id";
            }, array_keys($rawRepos));
            foreach(Mysql::query("SELECT r.repoId AS rid, p.projectId AS pid, p.name AS projectName,
                (SELECT COUNT(*) FROM builds WHERE builds.projectId=p.projectId 
                        AND builds.class IS NOT NULL) AS buildCnt,
                IFNULL((SELECT CONCAT_WS(',', buildId, internal) FROM builds WHERE builds.projectId = p.projectId
                        AND builds.class = ? ORDER BY created DESC LIMIT 1), 'null') AS buildNumber
                FROM projects p INNER JOIN repos r ON p.repoId=r.repoId WHERE r.build=1 AND (" .
                implode(" OR ", $ids) . ") ORDER BY r.name, projectName", "i", ProjectBuilder::BUILD_CLASS_DEV) as $projRow) {
                $project = new ProjectThumbnail();
                $project->id = (int) $projRow["pid"];
                $project->name = $projRow["projectName"];
                $project->buildCount = (int) $projRow["buildCnt"];
                if($projRow["buildNumber"] === "null") {
                    $project->latestBuildGlobalId = null;
                    $project->latestBuildInternalId = null;
                } else list($project->latestBuildGlobalId, $project->latestBuildInternalId) = array_map("intval", explode(",", $projRow["buildNumber"]));
                $repo = $rawRepos[(int) $projRow["rid"]];
                $project->repo = $repo;
                $repo->projects[] = $project;
            }
            if(isset($repo)) {
                $this->repoObj = $repo;
            }
            $this->projectsCount = count($this->repoObj->projects);
            if(isset($_POST["manifestFile"], $_POST["manifestContent"])) {
                $manifestFile = $_POST["manifestFile"];
                $manifestContent = $_POST["manifestContent"];
                $myName = Session::getInstance()->getName();
                $post = [
                    "message" => "Create $manifestFile\r\n" .
                        "Poggit-CI is enabled for this repo by @$myName\r\n" .
                        "Visit the Poggit-CI page for this repo at " . Meta::getSecret("meta.extPath") . "ci/" . $this->repoObj->full_name,
                    "content" => base64_encode($manifestContent),
                    "branch" => $this->repoObj->default_branch,
                    "committer" => ["name" => Meta::getSecret("meta.name"), "email" => Meta::getSecret("meta.email")]
                ];
                try {
                    $nowContent = GitHub::ghApiGet("repos/" . $this->repoObj->full_name . "/contents/" . $_POST["manifestFile"], $this->token);
                    $post["sha"] = $nowContent->sha;
                } catch(GitHubAPIException $e) {
                }

                GitHub::ghApiCustom("repos/" . $this->repoObj->full_name . "/contents/" . $_POST["manifestFile"], "PUT", $post, $this->token);
            }
        }

        Meta::getLog()->i(($this->enabled ? "Enabled" : "Disabled") . " CI for {$this->repoObj->full_name} upon request from " . Session::getInstance()->getName());

        // response
        echo json_encode([
            "success" => true,
            "repoId" => $this->repoId,
            "enabled" => $this->enabled,
            "projectsCount" => $this->projectsCount ?? 0,
            "panelHtml" => $this->enabled ? $this->displayReposAJAX($this->repoObj) : "" //For the AJAX panel refresh,
        ]);
    }

    private function setupWebhooks(int $id, string $webhookKey) {
        $token = $this->token;
        if($id !== 0) {
            try {
                $hook = GitHub::ghApiGet("repos/$this->owner/$this->repoName/hooks/$id", $token);
                if($hook->config->url === GitHubWebhookModule::extPath() . "/" . bin2hex($webhookKey)) {
                    if(!$hook->active) {
                        GitHub::ghApiCustom("repos/$this->owner/$this->repoName/hooks/$hook->id", "PATCH", [
                            "active" => true,
                        ], $token);
                    }
                    return [$hook->id, $webhookKey];
                }
            } catch(GitHubAPIException $e) {
                if($e->getErrorMessage() !== "Not Found") {
                    throw $e;
                }
                // the webhook might have been deleted, let's reset it
            }
        }
        try {
            $webhookKey = random_bytes(8);
            $hook = GitHub::ghApiPost("repos/$this->owner/$this->repoName/hooks", [
                "name" => "web",
                "config" => [
                    "url" => GitHubWebhookModule::extPath() . "/" . bin2hex($webhookKey),
                    "content_type" => "json",
                    "secret" => Meta::getSecret("meta.hookSecret") . bin2hex($webhookKey),
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
            if($e->getErrorMessage() === "Not Found") {
                throw new RequireLoginException("Poggit does not have the authorization to setup your repo. Please enable the write:repo_hook scope in https://poggit.pmmp.io/login");
            }
            if($e->getErrorMessage() === "Validation failed") {
                Meta::getLog()->wtf("Webhook setup failed for repo $this->owner/$this->repoName due to duplicated config");
            }
            throw $e;
        }
    }

    private function displayReposAJAX($repo): string {
        $home = Meta::root();
        $panelHtml = "<div class='repo-toggle' data-name='$repo->full_name'"
            . " data-opened='true' id='repo-$repo->id'><h5><a href='$home/ci/$repo->full_name'></a>"
            . $this->displayUserAJAX($repo->owner)
            . " <br></h5><h6>" . $repo->name
            . "<a href='https://github.com/"
            . $repo->owner->login
            . "/" . $repo->name . "' target='_blank'>"
            . "<img class='gh-logo' src='" . Meta::root() . "res/ghMark.png' width='16'/></a>"
            . "</h6>";
        if(count($repo->projects) > 0) {
            foreach($repo->projects as $project) {
                $panelHtml .= "<div class='brief-info-wrapper'>";
                $panelHtml .= $this->thumbnailProjectAJAX($project);
                $panelHtml .= "</div>";
            }
        } else {
            $panelHtml .= "<div class='text-success'><h5>Building Repo</h5></div><p>This may take up to 1 minute. Refresh the page to check again</p>";
        }
        return $panelHtml . "</div>";
    }

    private function thumbnailProjectAJAX(ProjectThumbnail $project) {
        if($project->latestBuildInternalId !== null or $project->latestBuildGlobalId !== null) {
            $url = "ci/" . $project->repo->full_name . "/" . urlencode($project->name) . "/" . $project->latestBuildInternalId;
            $buildNumbers = $this->showBuildNumbersAJAX($project->latestBuildGlobalId, $project->latestBuildInternalId, $url);
        } else {
            $buildNumbers = "No builds yet";
        }

        $html = "<div class='brief-info' data-project-id='" . $project->id . "'><h5>"
            . "<a href='"
            . Meta::root() . "ci/" . $project->repo->full_name . "/" . urlencode($project->name) . "'>"
            . htmlspecialchars($project->name) . "</a></h5>"
            . "<p class='remark'>Total: " . $project->buildCount . " development build"
            . ($project->buildCount > 1 ? "s" : "") . "</p>"
            . "<p class='remark'>Last development build:" . $buildNumbers . "</p></div>";
        return $html;
    }

    private function displayUserAJAX($owner) {
        $result = "";
        if($owner->avatar_url !== "") {
            $result .= "<img src='" . $owner->avatar_url . "' width='16' onerror=\"this.src='/res/ghMark.png'; this.onerror=null;\"/> ";
        }
        $result .= $owner->login . " ";
        $result .= $this->ghLinkAJAX("https://github.com/" . $owner->login);
        return $result;
    }

    private function ghLinkAJAX($url) {
        $markUrl = Meta::root() . "res/ghMark.png";
        $result = "<a href='" . $url . "' target='_blank'>";
        $result .= "<img class='gh-logo' src='" . $markUrl . "' width='16'/>";
        $result .= "</a>";
        return $result;
    }

    private function showBuildNumbersAJAX(int $global, int $internal, string $link = "") {
        $result = "";
        if(strlen($link) > 0) {
            $result .= "<a href='" . Meta::root() . $link . "'>";
        }
        $result .= "<span style='font-family: \"Courier New\", monospace;'>#$internal (&amp;" . strtoupper(dechex($global)) . ")</span>";
        if(strlen($link) > 0) {
            $result .= "</a>";
        }
        $hexId = strtoupper(dechex($global));
        $result .= "<sup class='hover-title' title='#$internal is the internal build number for your project. &amp;$hexId is a unique build ID for all Poggit CI builds'>(?)</sup>";
        return $result;
    }
}
