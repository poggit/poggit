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

use Exception;
use poggit\account\Session;
use poggit\ci\RepoZipball;
use poggit\Meta;
use poggit\module\AjaxModule;
use poggit\utils\internet\CurlErrorException;
use poggit\utils\internet\GitHub;
use poggit\utils\lang\Lang;
use poggit\utils\OutputManager;
use RuntimeException;
use function explode;
use function is_object;
use function json_decode;
use function json_encode;
use function rtrim;
use function str_replace;
use function strlen;
use function strpos;
use function substr;
use function trim;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

class ScanRepoProjectsAjax extends AjaxModule {
    protected function impl() {
        $token = Session::getInstance()->getAccessToken();
        $repoId = (int) $this->param("repoId", $_POST);
        $repoObject = GitHub::ghApiGet("repositories/$repoId", $token);
        $zero = 0;
        try{
            $zipball = new RepoZipball("repositories/$repoId/zipball", $token, "repositories/$repoId", $zero, null, Meta::getMaxZipballSize($repoId));
        } catch(Exception $e) {
            OutputManager::terminateAll();
            if(($e instanceof CurlErrorException and $e->getMessage() === "Callback aborted") or ($e instanceof RuntimeException and $e->getMessage() === "File too large")){
                echo json_encode([
                    "status" => "error/bad_request",
                    "message" => "Repository too large, Max size allowed is ".(Meta::getMaxZipballSize($repoId)/1024/1024)."MB"
                ]);
                Meta::getLog()->e("Error scanning repository '$repoId', Repository too large.\n" . $e->getTraceAsString());
            } else {
                Meta::getLog()->e("Error scanning repository '$repoId', Unhandled error '" . $e->getMessage() . "'\n" . $e->getTraceAsString());
                http_response_code(500);
            }
            die();
        }
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
                "build-by-default" => true,
                "branches" => [$repoObject->default_branch],
                "projects" => $projects
            ];
            $yaml = yaml_emit($manifestData, YAML_UTF8_ENCODING, YAML_LN_BREAK);
            if(Lang::startsWith($yaml, "---\n")) {
                $yaml = "--- # Poggit-CI Manifest. Open the CI at " . Meta::getSecret("meta.extPath") .
                    "ci/{$repoObject->owner->login}/{$repoObject->name}" . "\n" . substr($yaml, 4);
            }
        }
        echo json_encode(["status" => "success", "yaml" => $yaml], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function projectPathToName(string $path, string $repoName) {
        return $path !== "" ? str_replace(["/", "?", "#", "&", "\\"], ".", rtrim($path, "/")) : $repoName;
    }
}
