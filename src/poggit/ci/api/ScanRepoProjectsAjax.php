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

namespace poggit\ci\api;

use poggit\account\Session;
use poggit\ci\RepoZipball;
use poggit\Meta;
use poggit\module\AjaxModule;
use poggit\utils\internet\Curl;
use poggit\utils\lang\Lang;

class ScanRepoProjectsAjax extends AjaxModule {
    protected function impl() {
        $token = Session::getInstance()->getAccessToken();
        $repoId = (int) $this->param("repoId", $_POST);
        $repoObject = Curl::ghApiGet("repositories/$repoId", $token);
        $zero = 0;
        $zipball = new RepoZipball("repositories/$repoId/zipball", $token, "repositories/$repoId", $zero, null, Meta::getMaxZipballSize($repoId));

        if($zipball->isFile(".poggit.yml")) {
            $yaml = $zipball->getContents(".poggit.yml");
        } elseif($zipball->isFile(".poggit/.poggit.yml")) {
            $yaml = $zipball->getContents(".poggit/.poggit.yml");
        } else {
            $projects = [];
            foreach($zipball->iterator("", true) as $path => $getCont) {
                if($path === "plugin.yml" or Lang::endsWith($path, "/plugin.yml")) {
                    $dir = substr($path, 0, -strlen("plugin.yml"));
                    if(!$zipball->isDirectory($dir . "src")) continue;
                    $name = $this->projectPathToName($dir, $repoObject->name);
                    $object = [
                        "path" => $dir,
                    ];
                    $projects[$name] = $object;
                } elseif($path === "compile.php" or Lang::endsWith($path, "/compile.php")) {
                    $dir = substr($path, 0, -strlen("compile.php"));
                    if(!$zipball->isDirectory($dir . "src")) continue;
                    if(!$zipball->isFile($dir . "nowhere.json")) continue;
                    foreach(explode("\n", $getCont()) as $line) {
                        if(trim($line) === "/*") {
                            $nowhereComment = true;
                        } elseif(isset($nowhereComment)) {
                            if(strpos($line, "* NOWHERE Plugin Workspace Framework")) {
                                $nowhereConfirmed = true;
                                break;
                            }
                        }
                    }
                    if(!isset($nowhereConfirmed)) continue;
                    $nowhereJson = json_decode($zipball->getContents($dir . "nowhere.json"));
                    if(!is_object($nowhereJson) or !isset($nowhereJson->name)) continue;
                    $object = [
                        "path" => $dir,
                        "model" => "nowhere"
                    ];
                    $projects[$nowhereJson->name] = $object;
                } elseif($path === "virion.yml" or Lang::endsWith($path, "/virion.yml")) {
                    $dir = substr($path, 0, -strlen("virion.yml"));
                    if(!$zipball->isDirectory($dir . "src")) continue;
                    $name = $this->projectPathToName($dir, $repoObject->name);
                    $object = [
                        "path" => $dir,
                        "model" => "virion",
                        "type" => "library"
                    ];
                    $projects[$name] = $object;
                }
            }

            $manifestData = [
                "branches" => [$repoObject->default_branch],
                "projects" => $projects
            ];
            $yaml = yaml_emit($manifestData, YAML_UTF8_ENCODING, YAML_LN_BREAK);
            if(Lang::startsWith($yaml, "---\n")) {
                $yaml = "--- # Poggit-CI Manifest. Open the CI at " . Meta::getSecret("meta.extPath") .
                    "ci/{$repoObject->owner->login}/{$repoObject->name}" . "\n" . substr($yaml, 4);
            }
        }
        echo json_encode(["yaml" => $yaml], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function projectPathToName(string $path, string $repoName) {
        return $path !== "" ? str_replace(["/", "?", "#", "&", "\\"], ".", rtrim($path, "/")) : $repoName;
    }
}
