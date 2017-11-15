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

namespace poggit\ci;

use Composer\Semver\Comparator;
use Composer\Semver\Semver;
use Phar;
use poggit\ci\builder\UserFriendlyException;
use poggit\Config;
use poggit\Meta;
use poggit\resource\ResourceManager;
use poggit\utils\internet\Curl;
use poggit\utils\internet\GitHubAPIException;
use poggit\utils\internet\Mysql;
use poggit\utils\lang\Lang;
use poggit\webhook\GitHubWebhookModule;
use poggit\webhook\WebhookHandler;
use poggit\webhook\WebhookProjectModel;
use stdClass;
use const poggit\ASSETS_PATH;
use const poggit\virion\VIRION_INFECTION_MODE_DOUBLE;
use const poggit\virion\VIRION_INFECTION_MODE_SINGLE;
use const poggit\virion\VIRION_INFECTION_MODE_SYNTAX;
use function poggit\virion\virion_infect;

class Virion {
    public $api;
    public $version;
    public $buildId;
    public $resourceId;
    public $created;
    public $buildNumber;

    public function __construct($api, $version, $buildId, $resourceId, $created, $buildNumber) {
        $this->api = $api;
        $this->version = $version;
        $this->buildId = $buildId;
        $this->resourceId = $resourceId;
        $this->created = $created;
        $this->buildNumber = $buildNumber;
    }

    public static function processLibs(Phar $phar, RepoZipball $zipball, WebhookProjectModel $project, callable $getPrefix) {
        require_once ASSETS_PATH . "php/virion.php";
        $libs = $project->manifest["libs"] ?? null;
        if(!is_array($libs)) return;
        $prefix = $getPrefix();
        foreach($libs as $libDeclaration) {
            echo "Processing library...\n";
            $format = $libDeclaration["format"] ?? "virion";
            if($format === "virion") {
                $shade = strtolower($libDeclaration["shade"] ?? "syntax");
                static $modes = [
                    "syntax" => VIRION_INFECTION_MODE_SYNTAX,
                    "single" => VIRION_INFECTION_MODE_SINGLE,
                    "double" => VIRION_INFECTION_MODE_DOUBLE
                ];
                if(!isset($modes[$shade])) {
                    GitHubWebhookModule::addWarning("Unknown shade mode '$shade', assumed 'syntax'");
                }
                $shade = $modes[$shade] ?? VIRION_INFECTION_MODE_SYNTAX;
                $vendor = strtolower($libDeclaration["vendor"] ?? "poggit-project");
                $epitope = $libDeclaration["epitope"] ?? "libs";
                if($epitope === ".none") {
                    $thisPrefix = $prefix;
                } elseif($epitope === ".sha") {
                    $thisPrefix = $prefix . "commit_" . substr($phar->getMetadata()["fromCommit"], 0, 7) . "\\";
                } elseif($epitope === ".random") {
                    $thisPrefix = $prefix . "_" . bin2hex(random_bytes(4)) . "\\";
                } else {
                    $epitope = trim($epitope, "\\");
                    if(preg_match(/** @lang RegExp */
                        '/^[A-Za-z_]\w*(\\\\[A-Za-z_]\w*)*$/i', $epitope)) {
                        $thisPrefix = $prefix . $epitope . "\\";
                    } else {
                        GitHubWebhookModule::addWarning("Invalid epitope $epitope, default value (`libs`) will be used.");
                        $thisPrefix = $prefix . "libs\\";
                    }
                }
                if($vendor === "raw") {
                    $src = $libDeclaration["src"] ?? "";
                    $file = self::resolveFile($src, $zipball, $project);
                    if(!is_file($file)) {
                        throw new \Exception("Cannot resolve raw virion vendor '$file'");
                    }
                    self::injectPharVirion($phar, $file, $thisPrefix, $shade);
                } else {
                    if($vendor !== "poggit-project") {
                        GitHubWebhookModule::addWarning("Unknown vendor $vendor, assumed 'poggit-project'");
                    }

                    if(!isset($libDeclaration["src"]) or
                        count($srcParts = Lang::explodeNoEmpty("/", trim($libDeclaration["src"], " \t\n\r\0\x0B/"))) === 0
                    ) {
                        GitHubWebhookModule::addWarning("One of the libs is missing 'src' attribute");
                        continue;
                    }
                    $srcProject = array_pop($srcParts);
                    $srcRepo = array_pop($srcParts) ?? $project->repo[1];
                    $srcOwner = array_pop($srcParts) ?? $project->repo[0];

                    $version = $libDeclaration["version"] ?? "*";
                    $branch = $libDeclaration["branch"] ?? ":default";

                    $virionBuildId = self::injectProjectVirion(WebhookHandler::$token, WebhookHandler::$user, $phar, $srcOwner, $srcRepo, $srcProject, $version, $branch, $thisPrefix, $shade);

                    Mysql::query("INSERT INTO virion_usages (virionBuild, userBuild) VALUES (?, ?)", "ii",
                        $virionBuildId, $phar->getMetadata()["poggitBuildId"]);
                }
            } elseif($format === "composer") {
                throw new \Exception("Composer is not supported yet");
            } else {
                throw new \Exception("Unknown virion format '$format'");
            }
        }
    }

    private static function injectProjectVirion(string $token, string $user, Phar $phar, string $owner, string $repo, string $project, string $version, string $branch, string $prefix, int $shade): int {
        $virion = self::findVirion("$owner/$repo", $project, $version, function($apis) {
            return true; // TODO implement API filtering
        }, $token, $user, $branch);

        echo "[*] Using virion version {$virion->version} from build #{$virion->buildNumber}\n";
        $virionFile = ResourceManager::getInstance()->getResource($virion->resourceId, "phar");
        self::injectPharVirion($phar, $virionFile, $prefix, $shade);
        return $virion->buildId;
    }

    /**
     * Find a virion build by repo+project+version[+branch], accessed with a certain access token/user
     *
     * @param int|string $repoIdentifier repoId OR "{$repoOwner}/{$repoName}"
     * @param string     $project
     * @param string     $versionConstraint
     * @param callable   $apiFilter
     * @param string     $accessToken
     * @param string     $accessUser
     * @param string     $branch
     * @return Virion
     * @throws UserFriendlyException
     */
    public static function findVirion($repoIdentifier, string $project, string $versionConstraint, callable $apiFilter, string $accessToken, string $accessUser = null, string $branch = ":default") {
        try {
            if($branch === ":default" || $accessUser === null) {
                $data = Curl::ghApiGet(is_numeric($repoIdentifier) ? "repositories/$repoIdentifier" : "repos/$repoIdentifier", $accessToken ?: Meta::getDefaultToken());
                if(!$data->permissions->pull) {
                    throw new GitHubAPIException("", new stdClass()); // immediately caught in the function
                }
                if($branch === ":default") $branch = $data->default_branch;
                $noBranch = false;
            } else {
                if(!Curl::testPermission($repoIdentifier, $accessToken, $accessUser, "pull")) {
                    throw new GitHubAPIException("", new stdClass());
                }
                if($branch === "*" || $branch === "%") {
                    $noBranch = true;
                }
            }
        } catch(GitHubAPIException $e) {
            throw new UserFriendlyException("No read access to repo $repoIdentifier");
        }
        $repoCondition = is_numeric($repoIdentifier) ? "repos.repoId=?" : "CONCAT(repos.owner, '/', repos.name) = ?";
        $rows = Mysql::query("SELECT v.version, v.api, v.buildId, b2.resourceId, UNIX_TIMESTAMP(b2.created) AS created, b2.internal
            FROM (SELECT MAX(virion_builds.buildId) AS buildId FROM virion_builds
                INNER JOIN builds ON virion_builds.buildId = builds.buildId
                INNER JOIN projects ON builds.projectId = projects.projectId
                INNER JOIN repos ON projects.repoId = repos.repoId
                WHERE $repoCondition AND projects.name=? AND (builds.branch=? OR ?) GROUP BY version) v1
            INNER JOIN virion_builds v ON v1.buildId = v.buildId
            INNER JOIN builds b2 ON v.buildId = b2.buildId",
            is_numeric($repoIdentifier) ? "issi" : "sssi", $repoIdentifier, $project, $branch, isset($noBranch) && $noBranch ? 1 : 0);
        $rows = array_values(array_filter($rows, function($row) use ($versionConstraint, $apiFilter) {
            return Semver::satisfies($row["version"], $versionConstraint) and $apiFilter(json_decode($row["api"]));
        }));
        if(count($rows) === 0) {
            throw new UserFriendlyException("No virion builds are available in $repoIdentifier/$project");
        }
        $best = $rows[0];
        foreach($rows as $row) {
            if(Comparator::greaterThan($row["version"], $best["version"])) {
                $best = $row;
            }
        }
        return new Virion(json_decode($best["api"]), $best["version"], (int) $best["buildId"], (int) $best["resourceId"], (int) $best["created"], (int) $best["internal"]);
    }

    private static function resolveFile(string $file, RepoZipball $zipball, WebhookProjectModel $project): string {
        $tmp = Meta::getTmpFile(".zip");
        if(Lang::startsWith($file, "http://") || Lang::startsWith($file, "https://")) {
            Curl::curlToFile($file, $tmp, Config::MAX_ZIPBALL_SIZE);
            if(Curl::$lastCurlResponseCode >= 400) {
                throw new \Exception("Error downloading virion from $file: HTTP " . Curl::$lastCurlResponseCode);
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
            virion_infect($virus, $host, $prefix, $shade, $hostChanges, $viralChanges);
            echo "Updated $hostChanges references in host and $viralChanges references in virus\n";
        } catch(\RuntimeException $e) {
            throw new UserFriendlyException($e->getMessage());
        }

    }
}
