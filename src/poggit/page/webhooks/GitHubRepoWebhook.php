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

namespace poggit\page\webhooks;

use poggit\page\Page;
use poggit\Poggit;
use poggit\resource\ResourceManager;
use function poggit\getInput;

class GitHubRepoWebhook extends Page {
    public static function extPath() {
        return Poggit::getSecret("meta.extPath") . "webhooks.gh.repo";
    }

    public function getName() : string {
        return "webhooks.gh.repo";
    }

    public function output() {
        Poggit::$plainTextOutput = true;
        header("Content-Type: text/plain");
        $headers = apache_request_headers();
        $input = getInput();
        $sig = $headers["X-Hub-Signature"] ?? "this should never match";
        if(strpos($sig, "=") === false) {
            $this->wrongSignature($sig);
            return;
        }
        list($algo, $recvHash) = explode("=", $sig, 2);
        $expectedHash = hash_hmac($algo, $input, Poggit::getSecret("meta.hookSecret"));
        if(!hash_equals($expectedHash, $recvHash)) {
            $this->wrongSignature($sig);
            return;
        }
        $payload = json_decode($input);
        switch($headers["X-GitHub-Event"]) {
            case "push":
                $this->onPush($payload);
                break;
        }
    }

    private function wrongSignature(string $signature) {
        Poggit::getLog()->w("Wrong webhook secret $signature from " . $_SERVER["REMOTE_ADDR"]);
        http_response_code(403);
        return;
    }

    private function onPush(\stdClass $payload) {
        echo "Handling push\n";
        // init data
        $branch = substr($payload->ref, 11);
        $repoObj = $payload->repository;

        // download repo info
        $repos = Poggit::queryAndFetch("SELECT owner, name, build, accessWith FROM repos WHERE repoId = ?", "i", $repoObj->id);
        if(count($repos) === 0) {
            http_response_code(401);
            echo "This repository has not been registered for Poggit";
            return;
        }
        $repo = $repos[0];
        // update repo name
        if($repo["owner"] !== $repoObj->owner->name or $repo["name"] !== $repoObj->name) { // update transferred repos
            echo "Updating rename\n";
            Poggit::queryAndFetch("UPDATE repos SET owner = ?, name = ? WHERE repoId = ?", "ssi",
                $repoObj->owner->name, $repoObj->name, $repoObj->id);
        }

        // start building
        // if build is disabled, do nothing
        if(((int) $repo["build"]) === 0) {
            echo "Build is disabled\n";
            return; // build disabled
        }

        // read old projects for repo
        $savedProjects = [];
        foreach(Poggit::queryAndFetch("SELECT projectId, name, lang,
                (SELECT IFNULL(MAX(internal), 0) FROM builds WHERE builds.projectId = projects.projectId AND type = ?) AS prevBuilds
            FROM projects WHERE repoId = ?", "ii", Poggit::BUILD_CLASS_DEV, $repoObj->id) as $savedProject) {
            $savedProjects[$savedProject["name"]] = $savedProject;
        }
        $ids = Poggit::queryAndFetch("SELECT (SELECT IFNULL(MAX(buildId), 100000) FROM builds) AS buildId,
                (SELECT IFNULL(MAX(projectId), 0) FROM projects) AS projectId")[0];
        $globId = (int) $ids["buildId"];
        $projectId = (int) $ids["projectId"];

        echo "Downloading repo files\n";
        $zipBall = Poggit::getTmpFile();
        file_put_contents($zipBall, Poggit::ghApiGet("repos/{$repoObj->full_name}/zipball/$branch", $repo["accessWith"], false, true));
        $zip = new \ZipArchive();
        $zip->open($zipBall);
        $prefix = $zip->getNameIndex(0);
        // search for manifest
        $fd = $zip->getStream("$prefix.poggit/.poggit.yml");
        if($fd === false) {
            $fd = $zip->getStream("$prefix.poggit.yml");
            if($fd === false) {
                echo ".poggit.yml not found!";
                return; // no .poggit.yml
            }
        }
        $manifest = yaml_parse(stream_get_contents($fd));
        fclose($fd);
        if(isset($manifest["branches"]) and !in_array(substr($payload->ref, 11), (array) $manifest["branches"])) {
            return; // don't build for this branch
        }
        foreach($manifest["projects"] as $name => $project) {
            echo "Scanning project $name\n";
            $project["name"] = $name;
            if(isset($savedProjects[$name])) {
                $projId = $savedProjects[$name]["projectId"];
                $prevBuilds = $savedProjects[$name]["prevBuilds"];
            } else {
                $projId = ++$projectId;
                echo "New project $name (#$projId)\n";
                Poggit::queryAndFetch("INSERT INTO projects (projectId, repoId, name, type, framework, lang) VALUES (?, ?, ?, ?, ?, ?)",
                    "iisisi", $projId, $repoObj->id, $name,
                    ($project["type"] ?? "plugin") === "library" ? Poggit::PROJECT_TYPE_LIBRARY : Poggit::PROJECT_TYPE_PLUGIN,
                    $project["model"] ?? "default", 0); // TODO validate model
                $prevBuilds = 0;
            }
            $this->buildProject($project, $zip, $payload, $projId, $prevBuilds, $globId);
        }

        http_response_code(201);
    }

    private function buildProject($project, \ZipArchive $zip, $payload, int $projectId, int $prevBuilds, int &$globId) {
        $path = trim(preg_replace('@[/\\\\]+@', "/", $project["path"]), "/");
        if($path !== "") $path .= "/";
        if($prevBuilds !== 0 and $path !== "") {
            $files = [];
            foreach($payload->commits as $commit) {
                $files = array_merge($files, $commit->added, $commit->removed, $commit->modified);
            }
            var_dump($files);
            foreach($files as $zipBall) {
                if(substr($zipBall, 0, strlen($path)) === $path) {
                    $changed = true;
                    break;
                }
            }
            if(!isset($changed)) {
                echo "Nothing changed in project " . $project["name"] . ", skipped\n";
                return;
            }
        }

        $internalId = $prevBuilds + 1;
        $globId++;
        // something of this project is changed in this push
        $model = $project["model"] ?? "default";
        switch(strtolower($model)) {
            case "default":
                list($phar, $resourceId, $file) = $this->buildDefault($project, $zip, $internalId, $globId);
                break;
            case "nowhere":
                list($phar, $resourceId, $file) = $this->buildNowHere($project, $zip, $internalId, $globId);
                break;
            default:
                // TODO create error: Unknown model
                return;
        }
        /** @var \Phar $phar */
        /** @var int $resourceId */
        /** @var string $file */

        if(isset($project["lang"])) {
            // TODO inject translations
        }
        // TODO inject libraries

//        $phar->compressFiles(\Phar::GZ);
        $phar->stopBuffering();
        Poggit::queryAndFetch("INSERT INTO builds (buildId, resourceId, projectId, class, internal) VALUES
            (?, ?, ?, ?, ?)", "iiiii", $globId, $resourceId, $projectId, Poggit::BUILD_CLASS_DEV, $internalId);

        echo "md5 checksum for project {$project["name"]}: " . md5_file($file) . "\n";
    }

    private function buildDefault(array $decl, \ZipArchive $zip, int $internalId, int $globId) : array {
        $prefix = $zip->getNameIndex(0);
        $prefixLength = strlen($prefix);
        echo "Using default builder\n";
        $file = ResourceManager::getInstance()->createResource("phar", 315360000, $id);
        $path = trim($decl["path"], "/");
        if($path !== "") $path .= "/";
        $phar = new \Phar($file);
        $phar->setStub(($zip->getFromName("stub.php") . '__HALT_COMPILER();') ?: '<?php __HALT_COMPILER();');
        $phar->setSignatureAlgorithm(\Phar::SHA1);
        $phar->startBuffering();
        echo "Scanning zip archive\n";
        for($i = 0; $i < $zip->numFiles; $i++) {
            $name = substr($zip->getNameIndex($i), $prefixLength);
            if(strlen($name) === 0) {
//                echo "Skipping root $name\n";
                continue;
            }
            if(strlen($name) < strlen($path) or substr($name, 0, strlen($path)) !== $path) {
//                echo "$name not in $path\n";
                continue;
            }
            if(substr($name, -1) === "/") {
//                echo "Skipping directory $name\n";
                continue;
            }
            $name = substr($name, strlen($path));
            if($name === "plugin.yml" or substr($name, 0, 4) === "src/" or substr($name, 0, 10) == "resources/") {
                $phar->addFromString($name, $zip->getFromIndex($i));
                echo "Adding file $name\n";
            }
        }
        $phar->setMetadata([
            "date" => time(),
            "internalId" => $internalId,
            "globId" => $globId,
            "builder" => "Poggit",
        ]);
        return [$phar, $id, $file];
    }

    private function buildNowHere(array $decl, \ZipArchive $zip, int $internalId, int $globId) : array {
        // TODO implement
    }
}
