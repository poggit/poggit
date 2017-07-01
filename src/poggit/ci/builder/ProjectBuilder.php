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

use Phar;
use poggit\ci\api\ProjectSubToggleAjax;
use poggit\ci\cause\V2BuildCause;
use poggit\ci\cause\V2PushBuildCause;
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
use poggit\Config;
use poggit\Meta;
use poggit\resource\ResourceManager;
use poggit\timeline\BuildCompleteTimeLineEvent;
use poggit\utils\internet\Curl;
use poggit\utils\internet\Mysql;
use poggit\utils\lang\Lang;
use poggit\utils\lang\NativeError;
use poggit\webhook\WebhookException;
use poggit\webhook\WebhookHandler;
use poggit\webhook\WebhookProjectModel;
use stdClass;

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
    public static $LIBRARY_BUILDERS = ["virion" => PoggitVirionBuilder::class];
    public static $SPOON_BUILDERS = ["spoon" => SpoonBuilder::class];
    private static $moreBuilds;

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
     * @throws WebhookException
     */
    public static function buildProjects(RepoZipball $zipball, stdClass $repoData, array $projects, array $commitMessages, array $changedFiles, V2BuildCause $cause, int $triggerUserId, callable $buildNumber, int $buildClass, string $branch, string $sha) {
        $cnt = (int) Mysql::query("SELECT COUNT(*) AS cnt FROM builds WHERE triggerUser = ? AND 
            UNIX_TIMESTAMP() - UNIX_TIMESTAMP(created) < 604800", "i", $triggerUserId)[0]["cnt"];

        /** @var WebhookProjectModel[] $needBuild */
        $needBuild = [];

        // parse commit message
        $needBuildNames = [];
        // loop_messages
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
                            break 3; // loop_messages
                        } elseif($name === "all") {
                            $needBuild = $projects;
                            $wild = true;
                            break 3; // loop_messages
                        } else {
                            $needBuildNames[] = strtolower(trim($name));
                        }
                    }
                }
            }
        }

        // scan needBuild projects
        if(!isset($wild)) {
            // loop_projects:
            foreach($projects as $project) {
                if($project->devBuilds === 0) {
                    $needBuild[] = $project;
                    continue;
                }
                foreach($needBuildNames as $name) {
                    $name = strtolower(trim($name));
                    if($name === strtolower($project->name)) {
                        $needBuild[] = $project;
                        continue 2; // loop_projects
                    } elseif($name === "none") {
                        continue 2; // loop_projects
                    }
                }
                foreach($changedFiles as $fileName) {
                    if(($fileName === ".poggit.yml" or $fileName === ".poggit/.poggit.yml") or Lang::startsWith($fileName, $project->path)) {
                        $needBuild[] = $project;
                        continue 2; // loop_projects
                    }
                }
            }
        }
        // declare pending
        foreach($needBuild as $project) {
            if($project->projectId !== 210) {
                Curl::ghApiPost("repos/" . ($repoData->owner->login ?? $repoData->owner->name) . // blame GitHub
                    "/{$repoData->name}/statuses/$sha", [
                    "state" => "pending",
                    "description" => "Build in progress",
                    "context" => $context = "poggit-ci/" . preg_replace('$ _/\.$', "-", $project->name)
                ], WebhookHandler::$token);
            }
        }
        self::$moreBuilds = count($needBuild);
        foreach($needBuild as $project) {
            if($cnt >= (Meta::getSecret("perms.buildQuota")[$triggerUserId] ?? Config::MAX_WEEKLY_BUILDS)) {
                throw new WebhookException("Resend this delivery later. This commit is triggered by user #$triggerUserId, who has created $cnt Poggit-CI builds in the past 168 hours.", WebhookException::LOG_IN_WARN | WebhookException::OUTPUT_TO_RESPONSE | WebhookException::NOTIFY_AS_COMMENT, $repoData->full_name, $cause->getCommitSha());
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
            --self::$moreBuilds;
            $builder->init($zipball, $repoData, $project, $cause, $triggerUserId, $buildNumber, $buildClass, $branch, $sha);
        }
    }

    private function init(RepoZipball $zipball, stdClass $repoData, WebhookProjectModel $project, V2BuildCause $cause, int $triggerUserId, callable $buildNumberGetter, int $buildClass, string $branch, string $sha) {
        $IS_PMMP = $repoData->id === 69691727;
        $buildId = (int) Mysql::query("SELECT IFNULL(MAX(buildId), 19200) + 1 AS nextBuildId FROM builds")[0]["nextBuildId"];
        Mysql::query("INSERT INTO builds (buildId, projectId, buildsAfterThis) VALUES (?, ?, ?)", "iii", $buildId, $project->projectId, self::$moreBuilds);
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
        $rsrFile = ResourceManager::getInstance()->createResource("phar", "application/octet-stream", $accessFilters, $rsrId, 315360000, "poggit.ci.build"); // TODO setup expiry

        $phar = new Phar($rsrFile);
        $phar->startBuffering();
        $phar->setSignatureAlgorithm(Phar::SHA1);
        if($IS_PMMP) {
            $metadata = [
                "builder" => "PoggitCI/" . Meta::POGGIT_VERSION . " " . $this->getName() . "/" . $this->getVersion(),
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
                "builder" => "PoggitCI/" . Meta::POGGIT_VERSION . "/" . Meta::$GIT_REF . " " . $this->getName() . "/" . $this->getVersion(),
                "builderName" => "poggit",
                "buildTime" => date(DATE_ISO8601),
                "poggitBuildId" => $buildId,
                "buildClass" => $buildClassName,
                "projectBuildNumber" => $buildNumber,
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
                "friendly" => $e instanceof UserFriendlyException
            ];
            if(Meta::isDebug()) {
                echo "Encountered error: " . json_encode($status) . "\n";
            } else {
                echo "Encountered error\n";
            }
            $buildResult->statuses = [$status];
        }

        $classTree = [];
        foreach($buildResult->knownClasses as $class) {
            $parts = explode("\\", $class);
            $pointer =& $classTree;
            foreach(array_slice($parts, 0, -1) as $part) {
                if(!isset($pointer[$part])) $pointer[$part] = [];
                $pointer =& $pointer[$part];
            }
            $pointer[end($parts)] = true;
        }

        $this->knowClasses($buildId, $classTree);

        if($project->manifest["compressBuilds"] ?? true) $phar->compressFiles(\Phar::GZ);
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
        Mysql::query("UPDATE builds SET resourceId = ?, class = ?, branch = ?, sha = ?, cause = ?, internal = ?, triggerUser = ? WHERE buildId = ?",
            "iisssiii", $rsrId, $buildClass, $branch, $sha, json_encode($cause, JSON_UNESCAPED_SLASHES), $buildNumber,
            $triggerUserId, $buildId);
        $buildResult->storeMysql($buildId);
        $event = new BuildCompleteTimeLineEvent;
        $event->buildId = $buildId;
        $event->name = $project->name;
        $eventId = $event->dispatch();
        Mysql::query("INSERT INTO user_timeline (eventId, userId) SELECT ?, userId FROM project_subs WHERE projectId = ? AND level >= ?",
            "iii", $eventId, $project->projectId, $cause instanceof V2PushBuildCause ? ProjectSubToggleAjax::LEVEL_DEV_BUILDS : ProjectSubToggleAjax::LEVEL_DEV_AND_PR_BUILDS);

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
            Curl::ghApiPost("repos/" . ($repoData->owner->login ?? $repoData->owner->name) . // blame GitHub
                "/{$repoData->name}/statuses/$sha", $statusData = [
                "state" => BuildResult::$states[$buildResult->worstLevel],
                "target_url" => Meta::getSecret("meta.extPath") . "babs/" . dechex($buildId),
                "description" => $desc = "Created $buildClassName build #$buildNumber (&$buildId): "
                    . (count($messages) > 0 ? implode(", ", $messages) : "lint passed"),
                "context" => "poggit-ci/$project->name"
            ], WebhookHandler::$token);
            echo $statusData["context"] . ": " . $statusData["description"] . ", " . $statusData["state"] . " - " . $statusData["target_url"] . "\n";
        }
    }

    protected function knowClasses(int $buildId, array $classTree, string $prefix = "", int $prefixId = null, int $depth = 0) {
        foreach($classTree as $name => $children) {
            if(is_array($children)) {
                $insertId = Mysql::query("INSERT INTO namespaces (name, parent, depth) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE nsid = LAST_INSERT_ID(nsid)", "sii", $prefix . $name, $prefixId, $depth)->insert_id;
                $this->knowClasses($buildId, $children, $prefix . $name . "\\", $insertId, $depth + 1);
            } else {
                $insertId = Mysql::query("INSERT INTO known_classes (parent, name) VALUES (?, ?) ON DUPLICATE KEY UPDATE clid = LAST_INSERT_ID(clid)", "is", $prefixId, $name)->insert_id;
                Mysql::query("INSERT INTO class_occurrences (clid, buildId) VALUES (?, ?)", "ii", $insertId, $buildId);
            }
        }
    }

    public abstract function getName(): string;

    public abstract function getVersion(): string;

    protected abstract function build(Phar $phar, RepoZipball $zipball, WebhookProjectModel $project): BuildResult;

    protected function lintManifest(RepoZipball $zipball, BuildResult $result, string &$yaml, string &$mainClass = null): string {
        try {
            $manifest = @yaml_parse($yaml);
        } catch(\RuntimeException $e) {
            $manifest = $e->getMessage();
        } catch(NativeError $e) {
            $manifest = $e->getMessage();
        }
        if(!is_array($manifest)) {
            $error = new ManifestCorruptionBuildError();
            $error->manifestName = "plugin.yml";
            $error->message = $manifest;
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
        Lang::myShellExec("php -l " . escapeshellarg($this->tempFile), $stdout, $lint, $exitCode);
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
