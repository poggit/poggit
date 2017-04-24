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
use Phar;
use poggit\ci\cause\V2BuildCause;
use poggit\ci\lint\BuildResult;
use poggit\ci\lint\CloseTagLint;
use poggit\ci\lint\DirectStdoutLint;
use poggit\ci\lint\InternalBuildError;
use poggit\ci\lint\MainClassMissingLint;
use poggit\ci\lint\MalformedClassNameLint;
use poggit\ci\lint\ManifestAttributeMissingBuildError;
use poggit\ci\lint\ManifestCorruptionBuildError;
use poggit\ci\lint\NonPsrLint;
use poggit\ci\lint\PharTooLargeBuildError;
use poggit\ci\lint\PluginNameTransformedLint;
use poggit\ci\lint\RestrictedPluginNameLint;
use poggit\ci\lint\SyntaxErrorLint;
use poggit\ci\RepoZipball;
use poggit\Poggit;
use poggit\resource\ResourceManager;
use poggit\timeline\BuildCompleteTimeLineEvent;
use poggit\utils\Config;
use poggit\utils\internet\CurlUtils;
use poggit\utils\internet\GitHubAPIException;
use poggit\utils\internet\MysqlUtils;
use poggit\utils\lang\LangUtils;
use poggit\webhook\NewGitHubRepoWebhookModule;
use poggit\webhook\RepoWebhookHandler;
use poggit\webhook\StopWebhookExecutionException;
use poggit\webhook\WebhookProjectModel;
use stdClass;
use const poggit\ASSETS_PATH;
use const poggit\virion\VIRION_INFECTION_MODE_DOUBLE;
use const poggit\virion\VIRION_INFECTION_MODE_SINGLE;
use const poggit\virion\VIRION_INFECTION_MODE_SYNTAX;
use function poggit\virion\virion_infect;

abstract class ProjectBuilder {
    const PROJECT_TYPE_PLUGIN = 1;
    const PROJECT_TYPE_LIBRARY = 2;
    const PROJECT_TYPE_SPOON = 3;
    public static $PROJECT_TYPE_HUMAN = [
        ProjectBuilder::PROJECT_TYPE_PLUGIN => "Plugin",
        ProjectBuilder::PROJECT_TYPE_LIBRARY => "Library",
        ProjectBuilder::PROJECT_TYPE_SPOON => "Spoon",
    ];

    const BUILD_CLASS_DEV = 1;
    const BUILD_CLASS_PR = 4;

    public static $BUILD_CLASS_HUMAN = [
        ProjectBuilder::BUILD_CLASS_DEV => "Dev",
        ProjectBuilder::BUILD_CLASS_PR => "PR"
    ];
    public static $BUILD_CLASS_IDEN = [
        ProjectBuilder::BUILD_CLASS_DEV => "dev",
        ProjectBuilder::BUILD_CLASS_PR => "pr"
    ];

    public static $PLUGIN_BUILDERS = [
        "default" => DefaultProjectBuilder::class,
        "nowhere" => NowHereProjectBuilder::class,
    ];
    public static $LIBRARY_BUILDERS = [
        "virion" => PoggitVirionBuilder::class,
    ];
    public static $SPOON_BUILDERS = ["spoon" => SpoonBuilder::class];

    protected $project;
    protected $tempFile;

    /**
     * @param RepoZipball           $zipball
     * @param stdClass              $repoData
     * @param WebhookProjectModel[] $projects
     * @param string[]              $commitMessages
     * @param string[]              $changedFiles
     * @param V2BuildCause          $cause
     * @param int                   $triggerUserId
     * @param callable              $buildNumber
     * @param int                   $buildClass
     * @param string                $branch
     * @param string                $sha
     *
     * @throws StopWebhookExecutionException
     */
    public static function buildProjects(RepoZipball $zipball, stdClass $repoData, array $projects, array $commitMessages, array $changedFiles, V2BuildCause $cause, int $triggerUserId, callable $buildNumber, int $buildClass, string $branch, string $sha) {
        $cnt = (int) MysqlUtils::query("SELECT COUNT(*) AS cnt FROM builds WHERE triggerUser = ? AND 
            UNIX_TIMESTAMP() - UNIX_TIMESTAMP(created) < 604800", "i", $triggerUserId)[0]["cnt"];

        /** @var WebhookProjectModel[] $needBuild */
        $needBuild = [];

        // parse commit message
        $needBuildNames = [];
        foreach($commitMessages as $message) {
            if(stripos($message, "[ci skip]") or stripos($message, "[poggit skip]") or stripos($message, "poggit shutup") or stripos($message, "poggit none of your business") or stripos($message, "poggit noyb") or stripos($message, "poggit shut up") or stripos($message, "poggit shutup")) {
                $needBuild = $needBuildNames = [];
                $wild = true;
                break;
            }
            if(preg_match_all('/poggit[:,] (please )?(build|ci) (please )?([a-z0-9\-_., ]+)/i', $message, $matches)) {
                foreach($matches[2] as $match) {
                    foreach(array_filter(explode(",", $match), "string_not_empty") as $name) {
                        if($name === "none" or $name === "shutup" or $name === "shut up" or $name === "none of your business" or $name === "noyb") {
                            $needBuild = $needBuildNames = [];
                            $wild = true;
                            break 3;
                        } elseif($name === "all") {
                            $needBuild = $projects;
                            $wild = true;
                            break 3;
                        } else {
                            $needBuildNames[] = strtolower(trim($name));
                        }
                    }
                }
            }
        }

        // scan needBuild projects
        if(!isset($wild)) {
            foreach($projects as $project) {
                if($project->devBuilds === 0) {
                    $needBuild[] = $project;
                    continue;
                }
                foreach($needBuildNames as $name) {
                    $name = strtolower(trim($name));
                    if($name === strtolower($project->name)) {
                        $needBuild[] = $project;
                        continue 2;
                    } elseif($name === "none") {
                        continue 2;
                    }
                }
                foreach($changedFiles as $fileName) {
                    if(($fileName === ".poggit.yml" or $fileName === ".poggit/.poggit.yml") or LangUtils::startsWith($fileName, $project->path)) {
                        $needBuild[] = $project;
                        continue 2;
                    }
                }
            }
        }
        // declare pending
        foreach($needBuild as $project) {
            if($project->projectId !== 210) {
                CurlUtils::ghApiPost("repos/" . ($repoData->owner->login ?? $repoData->owner->name) . // blame GitHub
                    "/{$repoData->name}/statuses/$sha", [
                    "state" => "pending",
                    "description" => "Build in progress",
                    "context" => $context = "poggit-ci/" . preg_replace('$ _/\.$', "-", $project->name)
                ], RepoWebhookHandler::$token);
            }
        }
        foreach($needBuild as $project) {
            if($cnt >= (Poggit::getSecret("perms.buildQuota")[$triggerUserId] ?? Config::MAX_WEEKLY_BUILDS)) {
                throw new StopWebhookExecutionException("Resend this delivery later. This commit is triggered by user #$triggerUserId, who has created $cnt Poggit-CI builds in the past 168 hours.", 1);
            }
            $cnt++;
            $modelName = $project->framework;
            if($project->type === self::PROJECT_TYPE_LIBRARY) {
                $builderList = self::$LIBRARY_BUILDERS;
            } elseif($project->type === self::PROJECT_TYPE_SPOON) {
                $builderList = self::$SPOON_BUILDERS;
            } else {
                $builderList = self::$PLUGIN_BUILDERS;
            }
            $builderClass = $builderList[strtolower($modelName)];
            /** @var ProjectBuilder $builder */
            $builder = new $builderClass();
            $builder->init($zipball, $repoData, $project, $cause, $triggerUserId, $buildNumber, $buildClass, $branch, $sha);
        }
    }

    private function init(RepoZipball $zipball, stdClass $repoData, WebhookProjectModel $project, V2BuildCause $cause, int $triggerUserId, callable $buildNumberGetter, int $buildClass, string $branch, string $sha) {
        $IS_PMMP = $repoData->id === 69691727;
        $buildId = (int) MysqlUtils::query("SELECT IFNULL(MAX(buildId), 19200) + 1 AS nextBuildId FROM builds")[0]["nextBuildId"];
        MysqlUtils::query("INSERT INTO builds (buildId, projectId) VALUES (?, ?)", "ii", $buildId, $project->projectId);
        $buildNumber = $buildNumberGetter($project);
        $buildClassName = self::$BUILD_CLASS_HUMAN[$buildClass];

        $accessFilters = [];
        if($repoData->private) {
            $accessFilters[] = [
                "type" => "repoAccess",
                "repo" => [
                    "id" => $repoData->id,
                    "owner" => $repoData->owner->name,
                    "name" => $repoData->name,
                    "requiredPerms" => ["pull"]
                ]
            ];
        }
        $rsrFile = ResourceManager::getInstance()->createResource("phar", "application/octet-stream", $accessFilters, $rsrId);

        $phar = new Phar($rsrFile);
        $phar->startBuffering();
        $phar->setSignatureAlgorithm(Phar::SHA1);
        if($IS_PMMP) {
            $metadata = [
                "builder" => "PoggitCI/" . Poggit::POGGIT_VERSION . " " . $this->getName() . "/" . $this->getVersion(),
                "poggitBuildId" => $buildId,
                "projectBuildNumber" => $buildNumber,
                "class" => $buildClassName,
                "name" => "PocketMine-MP",
                "creationDate" => time(),
            ];
            $pmphp = $zipball->getContents("src/pocketmine/PocketMine.php") . $zipball->getContents("src/pocketmine/network/protocol/Info.php");
            preg_match_all('/^[\t ]*const ([A-Z_]+) = (".*"|[0-9a-fx]+);$/', $pmphp, $matches, PREG_SET_ORDER);
            foreach($matches as $match) {
                $stdTr = ["VERSION" => "version", "CODENAME" => "codename", "MINECRAFT_VERSION" => "minecraft", "CURRENT_PROTOCOL" => "protocol", "API_VERSION" => "api"];
                $metadata[$stdTr[$match[1]]] = json_decode($match[2]);
            }
        } else {
            $metadata = [
                "builder" => "PoggitCI/" . Poggit::POGGIT_VERSION . " " . $this->getName() . "/" . $this->getVersion(),
                "buildTime" => date(DATE_ISO8601),
                "poggitBuildId" => $buildId,
                "projectBuildNumber" => $buildNumber,
                "class" => $buildClassName
            ];
        }
        $phar->setMetadata($metadata);

        try {
            $buildResult = $this->build($phar, $zipball, $project);
        } catch(\Throwable $e) {
            $buildResult = new BuildResult();
            $buildResult->worstLevel = BuildResult::LEVEL_BUILD_ERROR;
            $status = new InternalBuildError();
            $status->exception = [
                "class" => get_class($e),
                "message" => $e->getMessage(),
                "file" => $e->getFile(),
                "line" => $e->getLine(),
                "code" => $e->getCode(),
            ];
            if(Poggit::isDebug()) {
                echo "Encountered error: " . json_encode($status) . "\n";
            }else{
                echo "Encountered error\n";
            }
            $buildResult->statuses = [$status];
        }

        $classTree = [];
        Poggit::getLog()->d(json_encode($buildResult->knownClasses));
        foreach($buildResult->knownClasses as $class) {
            $parts = explode("\\", $class);
            $pointer =& $classTree;
            foreach(array_slice($parts, 0, -1) as $part) {
                if(!isset($pointer[$part])) $pointer[$part] = [];
                $pointer =& $pointer[$part];
            }
            $pointer[end($parts)] = true;
        }
        Poggit::getLog()->d(json_encode($classTree));

        $this->knowClasses($buildId, $classTree);

        $phar->compressFiles(\Phar::GZ);
        $phar->stopBuffering();
        $maxSize = Config::MAX_PHAR_SIZE;
        if(!$IS_PMMP and ($size = filesize($rsrFile)) > $maxSize) {
            $status = new PharTooLargeBuildError();
            $status->size = $size;
            $status->maxSize = $maxSize;
            $buildResult->addStatus($status);
        }

        if($buildResult->worstLevel === BuildResult::LEVEL_BUILD_ERROR) {
            $rsrId = ResourceManager::NULL_RESOURCE;
            @unlink($rsrFile);
        }
        MysqlUtils::query("UPDATE builds SET resourceId = ?, class = ?, branch = ?, sha = ?, cause = ?, internal = ?, triggerUser = ? WHERE buildId = ?",
            "iisssiii", $rsrId, $buildClass, $branch, $sha, json_encode($cause, JSON_UNESCAPED_SLASHES), $buildNumber,
            $triggerUserId, $buildId);
        $buildResult->storeMysql($buildId);
        $event = new BuildCompleteTimeLineEvent;
        $event->buildId = $buildId;
        $eventId = $event->dispatch();
        MysqlUtils::query("INSERT INTO user_timeline (eventId, userId) SELECT ?, userId FROM project_subs WHERE projectId = ?",
            "ii", $eventId, $project->projectId);

        $lintStats = [];
        foreach($buildResult->statuses as $status) {
            switch($status->level) {
                case BuildResult::LEVEL_BUILD_ERROR:
                    $lintStats["build error"] = ($lintStats["build error"] ?? 0) + 1;
                    break;
                case BuildResult::LEVEL_ERROR:
                    $lintStats["error"] = ($lintStats["error"] ?? 0) + 1;
                    break;
                case BuildResult::LEVEL_WARN:
                    $lintStats["warning"] = ($lintStats["warning"] ?? 0) + 1;
                    break;
                case BuildResult::LEVEL_LINT:
                    $lintStats["lint"] = ($lintStats["lint"] ?? 0) + 1;
                    break;
            }
        }
        $messages = [];
        foreach($lintStats as $type => $count) {
            $messages[] = $count . " " . $type . ($count > 1 ? "s" : "") . ", ";
        }
        if(!$IS_PMMP) {
            CurlUtils::ghApiPost("repos/" . ($repoData->owner->login ?? $repoData->owner->name) . // blame GitHub
                "/{$repoData->name}/statuses/$sha", $statusData = [
                "state" => BuildResult::$states[$buildResult->worstLevel],
                "target_url" => Poggit::getSecret("meta.extPath") . "babs/" . dechex($buildId),
                "description" => $desc = "Created $buildClassName build #$buildNumber (&$buildId): "
                    . (count($messages) > 0 ? implode(", ", $messages) : "lint passed"),
                "context" => "poggit-ci/$project->name"
            ], RepoWebhookHandler::$token);
            echo $statusData["context"] . ": " . $statusData["description"] . ", " . $statusData["state"] . " - " . $statusData["target_url"] . "\n";
        }
    }

    protected function knowClasses(int $buildId, array $classTree, string $prefix = "", int $prefixId = null, int $depth = 0) {
        foreach($classTree as $name => $children) {
            if(is_array($children)) {
                $insertId = MysqlUtils::query("INSERT INTO namespaces (name, parent, depth) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE nsid = LAST_INSERT_ID(nsid)", "sii", $prefix . $name, $prefixId, $depth)->insert_id;
                $this->knowClasses($buildId, $children, $prefix . $name . "\\", $insertId, $depth + 1);
            } else {
                $insertId = MysqlUtils::query("INSERT INTO known_classes (parent, name) VALUES (?, ?) ON DUPLICATE KEY UPDATE clid = LAST_INSERT_ID(clid)", "is", $prefixId, $name)->insert_id;
                MysqlUtils::query("INSERT INTO class_occurrences (clid, buildId) VALUES (?, ?)", "ii", $insertId, $buildId);
            }
        }
    }

    public abstract function getName(): string;

    public abstract function getVersion(): string;

    protected abstract function build(Phar $phar, RepoZipball $zipball, WebhookProjectModel $project): BuildResult;

    private static function matchesVersion($testVersion, $versionPattern): bool {
        return Semver::satisfies($testVersion, $versionPattern);
    }

    protected function processLibs(Phar $phar, RepoZipball $zipball, WebhookProjectModel $project, callable $getPrefix) {
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
                    NewGitHubRepoWebhookModule::addWarning("Unknown shade mode '$shade', assumed 'syntax'");
                }
                $shade = $modes[$shade] ?? VIRION_INFECTION_MODE_SYNTAX;
                $vendor = strtolower($libDeclaration["vendor"]??"poggit-project");
                if($vendor === "raw") {
                    $src = $libDeclaration["src"]??"";
                    $file = $this->resolveFile($src, $zipball, $project);
                    if(!is_file($file)) {
                        throw new \Exception("Cannot resolve raw virion vendor '$file'");
                    }
                    $this->injectPharVirion($phar, $file, $prefix, $shade);
                } else {
                    if($vendor !== "poggit-project") {
                        NewGitHubRepoWebhookModule::addWarning("Unknown vendor $vendor, assumed 'poggit-project'");
                    }

                    if(!isset($libDeclaration["src"]) or
                        count($srcParts = array_filter(explode("/", trim($libDeclaration["src"], " \t\n\r\0\x0B/")),
                            "string_not_empty")) === 0
                    ) {
                        NewGitHubRepoWebhookModule::addWarning("One of the libs is missing 'src' attribute");
                        continue;
                    }
                    $srcProject = array_pop($srcParts);
                    $srcRepo = array_pop($srcParts)?? $project->repo[1];
                    $srcOwner = array_pop($srcParts)??$project->repo[0];

                    $version = $libDeclaration["version"]??"*";
                    $branch = $libDeclaration["branch"][":default"];

                    $this->injectProjectVirion($phar, $srcOwner, $srcRepo, $srcProject, $version, $branch, $prefix, $shade);
                }
            } elseif($format === "composer") {
                throw new \Exception("Composer is not supported yet");
            } else {
                throw new \Exception("Unknown virion format '$format'");
            }
        }
    }

    private function injectProjectVirion(Phar $phar, string $owner, string $repo, string $project, string $version, string $branch, string $prefix, int $shade) {
        try {
            $data = CurlUtils::ghApiGet("repos/$owner/$repo", RepoWebhookHandler::$token);
            if(isset($data->permissions->pull) and !$data->permissions->pull) {
                throw new GitHubAPIException("", new stdClass());
            }
            if($branch === ":default") {
                $branch = $data->default_branch;
            }
        } catch(GitHubAPIException $e) {
            throw new \Exception("No read access to $owner/$repo/$project");
        }
        $rows = MysqlUtils::query("SELECT v.version, v.api, v.buildId, b2.resourceId, UNIX_TIMESTAMP(b2.created) AS created, b2.internal
            FROM (SELECT MAX(virion_builds.buildId) AS buildId FROM virion_builds
                INNER JOIN builds ON virion_builds.buildId = builds.buildId
                INNER JOIN projects ON builds.projectId = projects.projectId
                INNER JOIN repos ON projects.repoId = repos.repoId
                WHERE repos.owner=? AND repos.name=? AND projects.name=? AND builds.branch=? GROUP BY version) v1
            INNER JOIN virion_builds v ON v1.buildId = v.buildId
            INNER JOIN builds b2 ON v.buildId = b2.buildId", "ssss", $owner, $repo, $project, $branch);
        foreach($rows as $row) {
            if(ProjectBuilder::matchesVersion($row["version"], $version)) {
                // TODO check api acceptable
                if(Comparator::lessThanOrEqualTo(($good ?? $row)["version"], $row["version"]) and ($good ?? $row)["created"] <= $row["created"]) {
                    $good = $row;
                }
            }
        }
        if(!isset($good)) throw new \Exception("No matching virion versions");

        echo "[*] Using virion version $version from build #{$good["internal"]}\n";
        $virion = ResourceManager::getInstance()->getResource($good["resourceId"], "phar");
        $this->injectPharVirion($phar, $virion, $prefix, $shade);
    }

    private function injectPharVirion(Phar $host, string $virion, string $prefix, int $shade) {
        if(!is_file($virion)) throw new \InvalidArgumentException("Invalid virion provided");
        $virus = new Phar($virion);
        virion_infect($virus, $host, $prefix, $shade);
    }

    private function resolveFile(string $file, RepoZipball $zipball, WebhookProjectModel $project): string {
        $tmp = Poggit::getTmpFile(".zip");
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

    protected function lintManifest(RepoZipball $zipball, BuildResult $result, string &$yaml, string &$mainClass = null): string {
        try {
            $manifest = @yaml_parse($yaml);
        } catch(\RuntimeException $e) {
            $manifest = false;
        }
        if(!is_array($manifest)) {
            $error = new ManifestCorruptionBuildError();
            $error->manifestName = "plugin.yml";
            // TODO how to retrieve parse errors?
            $result->addStatus($error);
            return "/dev/null";
        }

        foreach(["name", "version", "main", "api"] as $attr) {
            if(!isset($manifest[$attr])) {
                $error = new ManifestAttributeMissingBuildError();
                $error->attribute = $attr;
                $result->addStatus($error);
            }
        }
        if(count($result->statuses) > 0) return "/dev/null";

        if(!preg_match('/^([A-Za-z0-9_]+\\\\)*[A-Za-z0-9_]+$/', $manifest["main"])) {
            $status = new MalformedClassNameLint();
            $status->className = $manifest["main"];
            $result->addStatus($status);
        }
        if(!$zipball->isFile($mainClassFile = $this->project->path . "src/" . str_replace("\\", "/", $mainClass = $manifest["main"]) . ".php")) {
            $status = new MainClassMissingLint();
            $status->expectedFile = $mainClassFile;
            $result->addStatus($status);
        }

        $name = str_replace(" ", "_", preg_replace("[^A-Za-z0-9 _.-]", "", $manifest["name"]));
        if($name !== $manifest["name"]) {
            $status = new PluginNameTransformedLint();
            $status->oldName = $manifest["name"];
            $status->fixedName = $name;
            $result->addStatus($status);

            $manifest["name"] = $name;
            $yaml = yaml_emit($manifest);
        }

        foreach(["pocketmine", "minecraft", "mojang"] as $restriction) {
            if(stripos($name, $restriction) !== false) {
                $status = new RestrictedPluginNameLint();
                $status->restriction = $restriction;
                $result->addStatus($status);
            }
        }

        return $mainClassFile;
    }

    protected function lintPhpFile(BuildResult $result, string $file, string $contents, bool $isFileMain, bool $doLint = true) {
        file_put_contents($this->tempFile, $contents);
        LangUtils::myShellExec("php -l " . escapeshellarg($this->tempFile), $stdout, $lint, $exitCode);
        if($exitCode !== 0) {
            $status = new SyntaxErrorLint();
            $status->file = $file;
            $status->output = $lint;
            $result->addStatus($status);
            return;
        }

        if($doLint) $this->checkPhp($result, $file, $contents, $isFileMain);
    }

    protected function checkPhp(BuildResult $result, string $iteratedFile, string $contents, bool $isFileMain) {
        $lines = explode("\n", $contents);
        $tokens = token_get_all($contents);
        $currentLine = 1;
        $namespaces = [];
        $classes = [];
        $wantClass = false;
        $currentNamespace = "";
        foreach($tokens as $t) {
            if(!is_array($t)) {
                $t = [-1, $t, $currentLine];
            }
            $lastToken = $token ?? [0, "", 0];
            list($tokenId, $currentCode, $currentLine) = $token = $t;
            $currentLine += substr_count($currentCode, "\n");

            if($tokenId === T_WHITESPACE) continue;
            if($tokenId === T_STRING) {
                if(isset($buildingNamespace)) {
                    $buildingNamespace .= trim($currentCode);
                } elseif($wantClass) {
                    $classes[] = [$currentNamespace, trim($currentCode), $currentLine];
                    $wantClass = false;
                }
            } elseif($tokenId === T_NS_SEPARATOR) {
                if(isset($buildingNamespace)) {
                    $buildingNamespace .= trim($currentCode);
                }
            } elseif($tokenId === T_CLASS) {
                if($lastToken[0] !== T_PAAMAYIM_NEKUDOTAYIM and $lastToken[0] !== T_NEW) $wantClass = true;
            } elseif($tokenId === T_NAMESPACE) {
                $buildingNamespace = "";
            } elseif($tokenId === -1) {
                if(trim($currentCode) === ";" || trim($currentCode) === "{" and isset($buildingNamespace)) {
                    $namespaces[] = $currentNamespace = $buildingNamespace;
                    unset($buildingNamespace);
                }
            } elseif($tokenId === T_CLOSE_TAG) {
                $status = new CloseTagLint();
                $status->file = $iteratedFile;
                $status->line = $currentLine;
                $status->code = $lines[$currentLine - 1] ?? "";
                $status->hlSects[] = [$closeTagPos = strpos($status->code, "\x3F\x3E"), $closeTagPos + 2];
                $result->addStatus($status);
            } elseif($tokenId === T_INLINE_HTML or $tokenId === T_ECHO) {
                if($tokenId === T_INLINE_HTML) {
                    if(isset($hasReportedInlineHtml)) continue;
                    $hasReportedInlineHtml = true;
                }
                if($tokenId === T_ECHO) {
                    if(isset($hasReportedUseOfEcho)) continue;
                    $hasReportedUseOfEcho = true;
                }
                $status = new DirectStdoutLint();
                $status->file = $iteratedFile;
                $status->line = $currentLine;
                if($tokenId === T_INLINE_HTML) {
                    $status->code = $currentCode;
                    $status->isHtml = true;
                } else {
                    $status->code = $lines[$currentLine - 1] ?? "";
                    $status->hlSects = [$hlSectsPos = stripos($status->code, "echo"), $hlSectsPos + 2];
                    $status->isHtml = false;
                }
                $status->isFileMain = $isFileMain;
                $result->addStatus($status);
            }
        }
        foreach($classes as list($namespace, $class, $line)) {
            $result->knownClasses[] = $namespace . "\\" . $class;
            if($iteratedFile !== "src/" . str_replace("\\", "/", $namespace) . "/" . $class . ".php") {
                $status = new NonPsrLint();
                $status->file = $iteratedFile;
                $status->line = $line;
                $status->code = $lines[$line - 1] ?? "";
                $status->hlSects = [$classPos = strpos($status->code, $class), $classPos + 2];
                $status->class = $namespace . "\\" . $class;
                $result->addStatus($status);
            }
        }
    }
}
