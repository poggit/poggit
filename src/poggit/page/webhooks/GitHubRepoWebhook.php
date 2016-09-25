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

    private function onPush(\stdClass $payload) {
        $branch = substr($payload->ref, 11);
        $zipBall = tempnam(sys_get_temp_dir(), "pog");
        file_put_contents($zipBall, Poggit::ghApiGet("repos/{$payload->repository->full_name}/zipball/$branch", "", false, true)); // fixme no token
        $zip = new \ZipArchive();
        $zip->open($zipBall);
        $fd = $zip->getStream(".poggit/.poggit.yml");
        if($fd === false) {
            $fd = $zip->getStream(".poggit.yml");
            if($fd === false) {
                return; // no .poggit.yml
            }
        }
        $manifest = yaml_parse(stream_get_contents($fd));
        fclose($fd);

        if(isset($manifest["branches"]) and !in_array(substr($payload->ref, 11), $manifest["branches"])) {
            return; // don't build for this project
        }
        foreach($manifest["projects"] as $name => $project) {
            $path = trim(preg_replace('@[/\\\\]+@', "/", $project["path"]), "/") . "/";
            if($path !== "") {
                foreach($payload->commits as $commit) {
                    foreach(["added", "removed", "modified"] as $k) {
                        foreach($commit->{$k} as $zipBall) {
                            if(substr($zipBall, 0, strlen($path)) === $path) {
                                $changed = true;
                                break 3;
                            }
                        }
                    }
                }
                if(!isset($changed)) {
                    continue;
                }
            }
            // something of this project is changed in this push
            $model = $project["model"] ?? "default";
            switch(strtolower($model)) {
                case "default":
                    $resource = $this->buildDefault($project, $zip);
                    break;
                case "nowhere":
                    $resource = $this->buildNowHere($project, $zip);
            }
        }
    }

    private function wrongSignature(string $signature) {
        Poggit::getLog()->w("Wrong webhook secret $signature from " . $_SERVER["REMOTE_ADDR"]);
        http_response_code(403);
        return;
    }

    private function buildDefault(array $decl, \ZipArchive $zip) : \Phar {
        $file = ResourceManager::getInstance()->createResource("phar", 315360000, $id);
        $path = $decl["path"];
        $phar = new \Phar($file);
        for($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if(substr($name, -1) === "/" or strlen($name) < strlen($path) or substr($name, 0, strlen($path)) !== $path) {
                continue;
            }
            if($name === "plugin.yml" or substr($name, 0, 4) === "src/" or substr($name, 0, 10) == "resources/") {
                $phar->addFromString($name, $zip->getFromIndex($i));
            }
        }
        return $phar;
    }

    private function buildNowHere(array $decl, \ZipArchive $zip) : \Phar {
        // TODO implement
    }
}
