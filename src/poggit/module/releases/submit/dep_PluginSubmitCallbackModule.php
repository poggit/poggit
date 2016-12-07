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

namespace poggit\module\releases\submit;

use poggit\builder\ProjectBuilder;
use poggit\exception\GitHubAPIException;
use poggit\model\PluginRelease;
use poggit\module\Module;
use poggit\Poggit;
use poggit\resource\ResourceManager;
use poggit\resource\ResourceNotFoundException;
use poggit\session\SessionUtils;

/**
 * Class dep_PluginSubmitCallbackModule
 *
 * @package poggit\module\releases\submit
 * @deprecated
 */
class dep_PluginSubmitCallbackModule extends Module {
    public function getName(): string {
        return "release.submit.callback";
    }

    public function output() {
        $session = SessionUtils::getInstance();
        if(!$session->isLoggedIn()) $this->errorAccessDenied();
        if(!isset($_POST["owner"])) $this->errorBadRequest("Missing POST field owner");
        if(!isset($_POST["repo"])) $this->errorBadRequest("Missing POST field \"repo\"");
        if(!isset($_POST["project"])) $this->errorBadRequest("Missing POST field \"project\"");
        if(!isset($_POST["build"])) $this->errorBadRequest("Missing POST field \"build\"");
        if(!isset($_POST["antiForge"])) $this->errorBadRequest("Missing POST field \"antiForge\"");
        if(!isset($_POST["name"])) $this->errorBadRequest("Missing POST field \"name\"");
        if(!isset($_POST["shortDesc"])) $this->errorBadRequest("Missing POST field \"shortDesc\"");
        if(!isset($_POST["version"])) $this->errorBadRequest("Missing POST field \"version\"");
        if(!isset($_POST["pluginDesc"])) $this->errorBadRequest("Missing POST field \"pluginDesc\"");
        if(!isset($_POST["pluginDescType"])) $this->errorBadRequest("Missing POST field \"pluginDescType\"");
        if(!isset($_POST["pluginChangeLog"])) $this->errorBadRequest("Missing POST field \"pluginChangeLog\"");
        if(!isset($_POST["pluginChangeLogType"])) $this->errorBadRequest("Missing POST field \"pluginChangeLogType\"");
        if(!isset($_POST["licenseType"])) $this->errorBadRequest("Missing POST field \"licenseType\"");
        if($_POST["licenseType"] === "custom" and !isset($_POST["licenseCustom"])) $this->errorBadRequest("Missing POST field \"licenseCustom\"");
        if(!isset($_POST["majorCategory"])) $this->errorBadRequest("Missing POST field \"majorCategory\"");
        if(!isset($_POST["minorCategories"])) $this->errorBadRequest("Missing POST field \"minorCategories\"");
        if(!isset($_POST["keywords"])) $this->errorBadRequest("Missing POST field \"keywords\"");
        if(!isset($_POST["isPreRelease"])) $this->errorBadRequest("Missing POST field \"isPreRelease\"");

        if($_POST["antiForge"] !== $session->getAntiForge()) $this->errorAccessDenied();

        $owner = $_POST["owner"];
        $repo = $_POST["repo"];

        try {
            $repoInfo = Poggit::ghApiGet("repos/$owner/$repo", $session->getAccessToken());
        } catch(GitHubAPIException$e) {
            $this->errorAccessDenied("The repo does not exist!");
            return;
        }
        if(!$repoInfo->permissions->admin) {
            $this->errorAccessDenied("You must have admin access to a repo to release it. " .
                "Your current access: " . str_replace("\n", ", ", yaml_emit($repoInfo->permissions)));
        }

        $project = $_POST["project"];
        $build = (int) $_POST["build"];

        $rows = Poggit::queryAndFetch("SELECT p.projectId, b.buildId, b.cause, b.resourceId FROM builds b 
            INNER JOIN projects p ON b.projectId = p.projectId WHERE p.repoId = ? AND p.name = ? AND b.class = ? AND b.internal = ?",
            "isii", $repoInfo->id, $project, ProjectBuilder::BUILD_CLASS_DEV, $build);
        if(count($rows) === 0) $this->errorBadRequest("The build does not exist!");
        $row = (object) $rows[0];

        try {
            $oldPharPath = ResourceManager::getInstance()->getResource($row->resourceId);
        } catch(ResourceNotFoundException $e) {
            $this->errorBadRequest("The build has been deleted!");
            return;
        }

        $newPharPath = ResourceManager::getInstance()->createResource("phar", "application/octet-stream", [], $artifactId);
        copy($oldPharPath, $newPharPath);
        $phar = new \Phar($newPharPath);
        $yaml = yaml_parse(file_get_contents($phar["plugin.yml"]->pathName));
        $yaml["version"] = $_POST["version"];
        $phar["plugin.yml"] = yaml_emit($yaml, YAML_UTF8_ENCODING, YAML_LN_BREAK);

        $release = new PluginRelease();
        $release->name = $_POST["name"];
        $release->shortDesc = $_POST["shortDesc"];
        $release->artifact = $artifactId;
        $release->projectId = $row->projectId;
        $release->version = $_POST["version"];
        $release->description = $this->mdOrTxt($_POST["pluginDesc"], $_POST["pluginDescType"], $_POST["owner"] . "/" . $_POST["repo"]);
        $release->icon = $this->saveIcon();
        $release->changeLog = $this->mdOrTxt($_POST["pluginChangeLog"], $_POST["pluginChangeLogType"], $_POST["owner"] . "/" . $_POST["repo"]);
        $release->license = $_POST["licenseType"];
        if($release->license === "custom") {
            file_put_contents(ResourceManager::getInstance()->createResource("txt", "text/plain", [], $licenseId), $_POST["licenseCustom"]);
            $release->licenseRes = $licenseId;
        }
        $release->flags = 0;
        if($_POST["isPreRelease"] === "on") $release->flags |= PluginRelease::RELEASE_FLAG_PRE_RELEASE;

        // TODO supported spoons
        // TODO dependencies
        // TODO permissions
        // TODO requirements / enhancements

        Poggit::queryAndFetch("INSERT INTO poggit.releases (name, shortDesc, artifact, projectId, version, description, icon, changelog, license, licenseRes, flags) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)", "ssiisiiisii",
            $release->name, $release->shortDesc, $release->artifact, $release->projectId, $release->version, $release->description, $release->icon, $release->changeLog, $release->license, $release->licenseRes ?? ResourceManager::NULL_RESOURCE, $release->flags);
    }

    private function mdOrTxt(string $text, string $type, string $repo): int {
        switch($type) {
            case "md":
                $data = Poggit::ghApiPost("markdown", ["text" => $text, "mode" => "gfm", "context" => $repo],
                    SessionUtils::getInstance()->getAccessToken(), true, ["Accept: application/vnd.github.v3"]);
                $format = "html";
                $mime = "text/html";
                break;
            case "txt":
                $data = htmlspecialchars($text);
                $format = "txt";
                $mime = "text/plain";
                break;
            default:
                $this->errorBadRequest("Unknown type '$type'");
                die;
        }
        file_put_contents(ResourceManager::getInstance()->createResource($format, $mime, [], $id), $data);
        return $id;
    }

    private function saveIcon(): int {
        if(!isset($_FILES["pluginIcon"])) return ResourceManager::NULL_RESOURCE;
        $name = $_FILES["pluginIcon"]["name"];
        $mime = $_FILES["pluginIcon"]["type"];
        if(!Poggit::startsWith($mime, "image/")) $this->errorBadRequest("Not an image!");
        $file = ResourceManager::getInstance()->createResource(substr($name, strrpos($name, ".") + 1), $mime, [], $id);
        move_uploaded_file($_FILES["pluginIcon"]["tmp_name"], $file);
        return $id;
    }
}
