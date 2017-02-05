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

namespace poggit\builder;

use Phar;
use poggit\builder\cause\V2BuildCause;
use poggit\builder\lint\BuildResult;
use poggit\builder\lint\CloseTagLint;
use poggit\builder\lint\DirectStdoutLint;
use poggit\builder\lint\InternalBuildError;
use poggit\builder\lint\NonPsrLint;
use poggit\builder\lint\PharTooLargeBuildError;
use poggit\module\webhooks\repo\RepoWebhookHandler;
use poggit\module\webhooks\repo\StopWebhookExecutionException;
use poggit\module\webhooks\repo\WebhookProjectModel;
use poggit\Poggit;
use poggit\resource\ResourceManager;
use poggit\timeline\BuildCompleteTimeLineEvent;
use poggit\utils\Config;
use poggit\utils\internet\CurlUtils;
use poggit\utils\internet\MysqlUtils;
use poggit\utils\lang\LangUtils;
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
    /** @deprecated */
    const BUILD_CLASS_BETA = 2;
    /** @deprecated */
    const BUILD_CLASS_RELEASE = 3;
    public static $BUILD_CLASS_HUMAN = [
        ProjectBuilder::BUILD_CLASS_DEV => "Dev",
//        ProjectBuilder::BUILD_CLASS_BETA => "Beta",
//        ProjectBuilder::BUILD_CLASS_RELEASE => "Release",
        ProjectBuilder::BUILD_CLASS_PR => "PR"
    ];
    public static $BUILD_CLASS_IDEN = [
        ProjectBuilder::BUILD_CLASS_DEV => "dev",
//        ProjectBuilder::BUILD_CLASS_BETA => "beta",
//        ProjectBuilder::BUILD_CLASS_RELEASE => "rc",
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
    public static function buildProjects(RepoZipball $zipball, stdClass $repoData, array $projects, array $commitMessages, array $changedFiles,
                                         V2BuildCause $cause, int $triggerUserId, callable $buildNumber, int $buildClass, string $branch, string $sha) {
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
                    foreach(array_filter(explode(",", $match)) as $name) {
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
            CurlUtils::ghApiPost("repos/" . ($repoData->owner->login ?? $repoData->owner->name) . // blame GitHub
                "/{$repoData->name}/statuses/$sha", [
                "state" => "pending",
                "description" => "Build in progress",
                "context" => $context = "poggit-ci/" . preg_replace('$ _/\.$', "-", $project->name)
            ], RepoWebhookHandler::$token);
        }
        foreach($needBuild as $project) {
            if($cnt >= Config::MAX_WEEKLY_BUILDS) {
                throw new StopWebhookExecutionException("Resend this delivery later. This user has created $cnt Poggit-CI builds in the past week.");
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

    private function init(RepoZipball $zipball, stdClass $repoData, WebhookProjectModel $project, V2BuildCause $cause, int $triggerUserId, callable $buildNumberGetter,
                          int $buildClass, string $branch, string $sha) {
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
            echo "Encountered error: " . json_encode($status);
            $buildResult->statuses = [$status];
        }

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

    public abstract function getName(): string;

    public abstract function getVersion(): string;

    protected abstract function build(Phar $phar, RepoZipball $zipball, WebhookProjectModel $project): BuildResult;

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
