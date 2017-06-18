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

namespace poggit\ci\builder;

use Composer\Semver\Comparator;
use Composer\Semver\Semver;
use Gajus\Dindent\Exception\RuntimeException;
use Phar;
use poggit\ci\RepoZipball;
use poggit\Config;
use poggit\Meta;
use poggit\resource\ResourceManager;
use poggit\utils\internet\CurlUtils;
use poggit\utils\internet\GitHubAPIException;
use poggit\utils\internet\MysqlUtils;
use poggit\utils\lang\LangUtils;
use poggit\webhook\GitHubWebhookModule;
use poggit\webhook\WebhookHandler;
use poggit\webhook\WebhookProjectModel;
use stdClass;
use const poggit\ASSETS_PATH;
use const poggit\virion\VIRION_INFECTION_MODE_DOUBLE;
use const poggit\virion\VIRION_INFECTION_MODE_SINGLE;
use const poggit\virion\VIRION_INFECTION_MODE_SYNTAX;
use function poggit\virion\virion_infect;

class LibManager {
    public static function processLibs(Phar $phar, RepoZipball $zipball, WebhookProjectModel $project, callable $getPrefix) {
        require_once ASSETS_PATH . "php/virion.php";
        $libs = $project->manifest["libs"] ?? null;
        if(!is_array($libs)) return;
        $prefix = $project->manifest["prefix"] ?? $getPrefix();
        foreach($libs as $libDeclaration) {
            echo "Processing library...\n";
            $format = $libDeclaration["format"] ?? "virion";
            if($format === "virion") {
                $shade = strtolower($libDeclaration["shade"]??"syntax");
                static $modes = [
                    "syntax" => VIRION_INFECTION_MODE_SYNTAX,
                    "single" => VIRION_INFECTION_MODE_SINGLE,
                    "double" => VIRION_INFECTION_MODE_DOUBLE
                ];
                if(!isset($modes[$shade])) {
                    GitHubWebhookModule::addWarning("Unknown shade mode '$shade', assumed 'syntax'");
                }
                $shade = $modes[$shade] ?? VIRION_INFECTION_MODE_SYNTAX;
                $vendor = strtolower($libDeclaration["vendor"]??"poggit-project");
                if($vendor === "raw") {
                    $src = $libDeclaration["src"]??"";
                    $file = LibManager::resolveFile($src, $zipball, $project);
                    if(!is_file($file)) {
                        throw new \Exception("Cannot resolve raw virion vendor '$file'");
                    }
                    LibManager::injectPharVirion($phar, $file, $prefix, $shade);
                } else {
                    if($vendor !== "poggit-project") {
                        GitHubWebhookModule::addWarning("Unknown vendor $vendor, assumed 'poggit-project'");
                    }

                    if(!isset($libDeclaration["src"]) or
                        count($srcParts = array_filter(explode("/", trim($libDeclaration["src"], " \t\n\r\0\x0B/")),
                            "string_not_empty")) === 0
                    ) {
                        GitHubWebhookModule::addWarning("One of the libs is missing 'src' attribute");
                        continue;
                    }
                    $srcProject = array_pop($srcParts);
                    $srcRepo = array_pop($srcParts)?? $project->repo[1];
                    $srcOwner = array_pop($srcParts)??$project->repo[0];

                    $version = $libDeclaration["version"] ?? "*";
                    $branch = $libDeclaration["branch"] ?? ":default";

                    LibManager::injectProjectVirion($phar, $srcOwner, $srcRepo, $srcProject, $version, $branch, $prefix, $shade);
                }
            } elseif($format === "composer") {
                throw new \Exception("Composer is not supported yet");
            } else {
                throw new \Exception("Unknown virion format '$format'");
            }
        }
    }

    private static function injectProjectVirion(Phar $phar, string $owner, string $repo, string $project, string $version, string $branch, string $prefix, int $shade) {
        try {
            $data = CurlUtils::ghApiGet("repos/$owner/$repo", WebhookHandler::$token);
            if(isset($data->permissions->pull) and !$data->permissions->pull) {
                throw new GitHubAPIException("", new stdClass());
            }
            if($branch === ":default") {
                $branch = $data->default_branch;
                $noBranch = false;
            } elseif($branch === "*" || $branch === "%") {
                $noBranch = true;
            }
        } catch(GitHubAPIException $e) {
            throw new UserFriendlyException("No read access to $owner/$repo/$project");
        }
        $rows = MysqlUtils::query("SELECT v.version, v.api, v.buildId, b2.resourceId, UNIX_TIMESTAMP(b2.created) AS created, b2.internal
            FROM (SELECT MAX(virion_builds.buildId) AS buildId FROM virion_builds
                INNER JOIN builds ON virion_builds.buildId = builds.buildId
                INNER JOIN projects ON builds.projectId = projects.projectId
                INNER JOIN repos ON projects.repoId = repos.repoId
                WHERE repos.owner=? AND repos.name=? AND projects.name=? AND (builds.branch=? OR ?) GROUP BY version) v1
            INNER JOIN virion_builds v ON v1.buildId = v.buildId
            INNER JOIN builds b2 ON v.buildId = b2.buildId",
            "ssssi", $owner, $repo, $project, $branch, isset($noBranch) && $noBranch ? 1 : 0);
        foreach($rows as $row) {
            if(Semver::satisfies($row["version"], $version)) {
                // TODO check api acceptable
                if(Comparator::lessThanOrEqualTo(($good ?? $row)["version"], $row["version"]) and ($good ?? $row)["created"] <= $row["created"]) {
                    $good = $row;
                }
            }
        }
        if(!isset($good)) throw new UserFriendlyException("No virion versions matching $version in $owner/$repo/$project");

        echo "[*] Using virion version {$good["version"]} from build #{$good["internal"]}\n";
        $virion = ResourceManager::getInstance()->getResource($good["resourceId"], "phar");
        LibManager::injectPharVirion($phar, $virion, $prefix, $shade);
    }

    private static function resolveFile(string $file, RepoZipball $zipball, WebhookProjectModel $project): string {
        $tmp = Meta::getTmpFile(".zip");
        if(LangUtils::startsWith($file, "http://") || LangUtils::startsWith($file, "https://")) {
            CurlUtils::curlToFile($file, $tmp, Config::MAX_ZIPBALL_SIZE);
            if(CurlUtils::$lastCurlResponseCode >= 400) {
                throw new \Exception("Error downloading virion from $file: HTTP " . CurlUtils::$lastCurlResponseCode);
            }
            return $tmp;
        }
        $rel = $file{0} === "/" ? substr($file, 1) : $project->path . $file;
        if(!$zipball->isFile($rel)) {
            throw new \Exception("Raw virion file is absent");
        }
        file_put_contents($tmp, $zipball->getContents($rel));
        return $tmp;
    }

    private static function injectPharVirion(Phar $host, string $virion, string $prefix, int $shade) {
        if(!is_file($virion)) throw new \InvalidArgumentException("Invalid virion provided");
        $virus = new Phar($virion);

        // flush host
        $host->stopBuffering();
        $host->startBuffering();

        try {
            virion_infect($virus, $host, $prefix, $shade);
        } catch(RuntimeException $e) {
            throw new UserFriendlyException($e->getMessage());
        }

    }
}
