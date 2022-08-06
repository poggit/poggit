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
use poggit\ci\TriggerUser;
use poggit\Config;
use poggit\Meta;
use poggit\resource\ResourceManager;
use poggit\timeline\BuildCompleteTimeLineEvent;
use poggit\utils\internet\Discord;
use poggit\utils\internet\GitHub;
use poggit\utils\internet\GitHubAPIException;
use poggit\utils\internet\Mysql;
use poggit\utils\lang\Lang;
use poggit\utils\lang\NativeError;
use poggit\webhook\WebhookException;
use poggit\webhook\WebhookHandler;
use poggit\webhook\WebhookProjectModel;
use RuntimeException;
use stdClass;
use Throwable;
use function array_keys;
use function array_merge;
use function array_slice;
use function count;
use function date;
use function dechex;
use function end;
use function escapeshellarg;
use function explode;
use function file_put_contents;
use function filesize;
use function fopen;
use function get_class;
use function gzclose;
use function gzopen;
use function implode;
use function is_array;
use function is_file;
use function is_int;
use function json_encode;
use function preg_match;
use function preg_match_all;
use function preg_replace;
use function rename;
use function sprintf;
use function str_replace;
use function stream_copy_to_stream;
use function stripos;
use function strpos;
use function strtolower;
use function substr;
use function substr_count;
use function token_get_all;
use function trim;
use function unlink;
use const DATE_ATOM;
use const JSON_UNESCAPED_SLASHES;
use const PREG_SET_ORDER;
use const T_CLASS;
use const T_CLOSE_TAG;
use const T_ECHO;
use const T_INLINE_HTML;
use const T_NAMESPACE;
use const T_NEW;
use const T_NAME_QUALIFIED;
use const T_PAAMAYIM_NEKUDOTAYIM;
use const T_STRING;
use const T_WHITESPACE;

abstract class ProjectBuilder {
    const PROJECT_TYPE_PLUGIN = 1;
    const PROJECT_TYPE_LIBRARY = 2;
    const PROJECT_TYPE_SPOON = 3;
    public static $PROJECT_TYPE_HUMAN = [
        self::PROJECT_TYPE_PLUGIN => "Plugin",
        self::PROJECT_TYPE_LIBRARY => "Library",
        self::PROJECT_TYPE_SPOON => "Spoon",
    ];

    const BUILD_CLASS_DEV = 1;
    const BUILD_CLASS_PR = 4;

    public static $BUILD_CLASS_HUMAN = [
        self::BUILD_CLASS_DEV => "Dev",
        self::BUILD_CLASS_PR => "PR"
    ];
    public static $BUILD_CLASS_SID = [
        self::BUILD_CLASS_DEV => "dev",
        self::BUILD_CLASS_PR => "pr"
    ];

    public static $PLUGIN_BUILDERS = [
        "default" => DefaultProjectBuilder::class,
        "nowhere" => NowHereProjectBuilder::class,
    ];
    public static $LIBRARY_BUILDERS = ["virion" => PoggitVirionBuilder::class];
    public static $SPOON_BUILDERS = ["spoon" => SpoonBuilder::class];
    private static $moreBuilds;

    private static $discordQueue = [];

    protected $project;
    protected $tempFile;

    /**
     * @param RepoZipball           $zipball
     * @param stdClass              $repoData
     * @param WebhookProjectModel[] $projects
     * @param stdClass[]            $commits
     * @param V2BuildCause          $cause
     * @param TriggerUser           $triggerUser
     * @param callable              $buildNumber
     * @param int                   $buildClass
     * @param string                $branch
     * @param string                $sha
     *
     * @param bool                  $buildByDefault
     * @throws WebhookException
     */
    public static function buildProjects(RepoZipball $zipball, stdClass $repoData, array $projects, array $commits, V2BuildCause $cause, TriggerUser $triggerUser, callable $buildNumber, int $buildClass, string $branch, string $sha, bool $buildByDefault) {
        $cnt = (int) Mysql::query("SELECT COUNT(*) AS cnt FROM builds WHERE triggerUser = ? AND 
            UNIX_TIMESTAMP() - UNIX_TIMESTAMP(created) < 604800", "i", $triggerUser->id)[0]["cnt"];
        echo "Starting from internal #$cnt\n";

        /** @var WebhookProjectModel[] $needBuild */
        $needBuild = [];
        $projectPaths = [];
        foreach($projects as $project) {
            $projectPaths[strtolower($project->name)] = $project->path;
        }
        foreach($commits as $commit) {
            foreach(self::findProjectsInCommit($projectPaths, $commit, $buildByDefault) as $projectName) {
                $needBuild[$projectName] = $projects[$projectName];
            }
        }

        if(count($needBuild) === 0) echo "No changes found, build cancelled.";

        // declare pending
        foreach($needBuild as $project) {
            echo "Build for {$project->name} pending\n";
            if($project->projectId !== 210) {
                try {
                    GitHub::ghApiPost("repos/" . ($repoData->owner->login ?? $repoData->owner->name) . // blame GitHub
                        "/{$repoData->name}/statuses/$sha", [
                        "state" => "pending",
                        "description" => "Build in progress",
                        "context" => $context = "poggit-ci/" . preg_replace('$ _/\.$', "-", $project->name)
                    ], WebhookHandler::$token);
                } catch(GitHubAPIException $e) {}
            }
        }
        self::$moreBuilds = count($needBuild);
        self::$discordQueue = [];
        foreach($needBuild as $project) {
            $limit = Meta::getSecret("perms.buildQuota")[$triggerUser->id] ?? Config::MAX_WEEKLY_BUILDS;
            if($cnt >= $limit) {
                Discord::throttleHook(<<<MESSAGE
@{$triggerUser->login} tried to create a build in {$project->name} in repo {$project->repo[0]}/{$project->repo[1]}, but he is blocked because he created too many builds ($cnt &ge; $limit) this week.
MESSAGE
                    , "Throttle audit");

                $discordInvite = Meta::getSecret("discord.serverInvite");
                throw new WebhookException(<<<MESSAGE
Resend this delivery later. This commit is triggered by user $triggerUser->login, who has created $cnt &ge; $limit Poggit-CI builds in the past 168 hours.

Contact SOFe [on Discord]($discordInvite) to request for extra quota. We will increase your quota for free if you are really contributing to open-source plugins actively without abusing Git commits.
MESSAGE
                    , WebhookException::LOG_INTERNAL | WebhookException::OUTPUT_TO_RESPONSE | WebhookException::NOTIFY_AS_COMMENT, $repoData->full_name, $cause->getCommitSha());
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
            $builder->init($zipball, $repoData, $project, $cause, $triggerUser, $buildNumber, $buildClass, $branch, $sha);
        }
        self::flushDiscordQueue();
    }

    private static function findProjectsInCommit(array $projectPaths, stdClass $commit, bool $buildByDefault): array {
        if(stripos($commit->message, "[ci skip]") !== false) {
            return [];
        }

        if(preg_match_all(/** @lang RegExp */
            '/poggit[:,]?\s+(?:please\s+)?(build|skip)/i', $commit->message, $matches, PREG_SET_ORDER)){
            if(count($matches) >= 1){
                $match = strtolower($matches[0][1]);
                if($match === "skip"){
                    echo "Skipping build as 'poggit skip' was detected in commit message.\n";
                    return [];
                }
                if($match === "build"){
                    echo "Building all projects as 'poggit build' was detected in commit message.\n";
                    return array_keys($projectPaths);
                }
            }
        }

        if(!$buildByDefault) {
            return [];
        }

        $changedProjects = [];

        foreach(array_merge($commit->added, $commit->removed, $commit->modified) as $file) {
            if($file === ".poggit.yml") {
                $changedProjects = $projectPaths;
                break;
            }
            if($file === "phpstan.neon" || $file === "phpstan.neon.dist") {
                $changedProjects = $projectPaths;
                break;
            }
            foreach($projectPaths as $project => $path) {
                if(Lang::startsWith($file, $path)) {
                    $changedProjects[$project] = true;
                }
            }
        }

        return array_keys($changedProjects);
    }

    private function init(RepoZipball $zipball, stdClass $repoData, WebhookProjectModel $project, V2BuildCause $cause, TriggerUser $triggerUser, callable $buildNumberGetter, int $buildClass, string $branch, string $sha) {
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
                    "owner" => $repoData->owner->login,
                    "name" => $repoData->name,
                    "requiredPerms" => ["pull"]
                ]
            ];
        }
        $rsrFile = ResourceManager::getInstance()->createResource("phar", "application/octet-stream", $accessFilters, $rsrId, 315360000, "poggit.ci.build", -1); // TODO setup expiry

        $phar = new Phar($rsrFile);
        $phar->startBuffering();
        $phar->setSignatureAlgorithm(Phar::SHA1);
        $metadata = [
            "builder" => "PoggitCI/" . Meta::POGGIT_VERSION . "/" . Meta::$GIT_REF . " " . $this->getName() . "/" . $this->getVersion(),
            "builderName" => "poggit",
            "buildTime" => date(DATE_ATOM),
            "poggitBuildId" => $buildId,
            "buildClass" => $buildClassName,
            "projectId" => $project->projectId,
            "projectBuildNumber" => $buildNumber,
            "fromCommit" => $sha,
            "poggitResourceId" => $rsrId,
        ];
        $phar->setMetadata($metadata);

        try {
            $buildResult = $this->build($phar, $zipball, $project, $buildId, $repoData->private);
            if($buildResult->worstLevel === BuildResult::LEVEL_BUILD_ERROR) {
                $phar->stopBuffering();
                goto errored;
            }
        } catch(Throwable $e) {
            $buildResult = new BuildResult();
            $buildResult->worstLevel = BuildResult::LEVEL_BUILD_ERROR;
            $status = new InternalBuildError();
            $status->exception = [
                "class" => get_class($e),
                "message" => $e->getMessage(),
                "file" => $e->getFile(),
                "line" => $e->getLine(),
                "code" => $e->getCode(),
                "trace" => $e->getTrace(),
                "friendly" => $e instanceof UserFriendlyException,
            ];
            if(Meta::isDebug()) {
                echo "Encountered error: " . json_encode($status) . "\n";
            } else {
                echo "Encountered error\n";
            }
            $buildResult->statuses = [$status];
            goto errored;
        }

        $classTree = [];
        foreach($buildResult->knownClasses as $class) {
            $parts = explode("\\", $class);
            $pointer =& $classTree;
            foreach(array_slice($parts, 0, -1) as $part) {
                if(!isset($pointer[$part]) || $pointer[$part] === true) {
                    $pointer[$part] = [];
                }
                $pointer =& $pointer[$part];
            }
            $pointer[end($parts)] = true;
        }

        $this->knowClasses($buildId, $classTree);

        if($project->manifest["compressBuilds"] ?? !($project->manifest["fullGzip"] ?? false)) {
            $phar->compressFiles(Phar::GZ);
        }
        $phar->stopBuffering();
        if($project->manifest["fullGzip"] ?? false) {
            $compression = 9;
            if(is_int($project->manifest["fullGzip"]) && 1 <= $project->manifest["fullGzip"] && $project->manifest["fullGzip"] <= 9) {
                $compression = (int) $project->manifest["fullGzip"];
            }
            rename($rsrFile, $tmp = Meta::getTmpFile(".phar"));
            $os = gzopen($rsrFile, "wb" . $compression);
            $is = fopen($tmp, "rb");
            stream_copy_to_stream($is, $os);
            gzclose($os);
            gzclose($is);
        }

        $maxSize = Config::MAX_PHAR_SIZE;
        if(($size = filesize($rsrFile)) > $maxSize) {
            $status = new PharTooLargeBuildError();
            $status->size = $size;
            $status->maxSize = $maxSize;
            $buildResult->addStatus($status);
        }
        Mysql::query("UPDATE resources SET fileSize = ? WHERE resourceId = ?", "ii", $size, $rsrId);

        errored:
        if($buildResult->worstLevel === BuildResult::LEVEL_BUILD_ERROR) {
            $rsrId = ResourceManager::NULL_RESOURCE;
            if(is_file($rsrFile)) {
                @unlink($rsrFile);
            }
        }
        $updates = [
            "resourceId" => ["i", $rsrId],
            "class" => ["i", $buildClass],
            "branch" => ["s", $branch],
            "sha" => ["s", $sha],
            "cause" => ["s", json_encode($cause, JSON_UNESCAPED_SLASHES)],
            "internal" => ["i", $buildNumber],
            "triggerUser" => ["i", $triggerUser->id],
            "path" => ["s", $project->path],
            "main" => ["s", $buildResult->main],
        ];
        Mysql::updateRow("builds", $updates, "buildId = ?", "i", $buildId);
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

        $lintMessage = count($messages) > 0 ? implode(", ", $messages) : "Lint passed";

        $buildPath = Meta::getSecret("meta.extPath") . "babs/" . dechex($buildId);
        try {
            GitHub::ghApiPost("repos/" . ($repoData->owner->login ?? $repoData->owner->name) . // blame GitHub
                "/{$repoData->name}/statuses/$sha", $statusData = [
                "state" => BuildResult::$states[$buildResult->worstLevel],
                "target_url" => $buildPath,
                "description" => $desc = "Created $buildClassName build #$buildNumber (&$buildId): "
                    . $lintMessage,
                "context" => "poggit-ci/$project->name"
            ], WebhookHandler::$token);
        } catch(GitHubAPIException $e) {}
        echo $statusData["context"] . ": " . $statusData["description"] . ", " . $statusData["state"] . " - " . $statusData["target_url"] . "\n";

        if(!$repoData->private) {
            $projectType = self::$PROJECT_TYPE_HUMAN[$project->type];

            $fields = [];
            $fields[] = [
                "name" => "Lint",
                "value" => $lintMessage
            ];
            if($rsrId !== ResourceManager::NULL_RESOURCE) {
                $fields[] = [
                    "name" => "Download link",
                    "value" => "https://poggit.pmmp.io/r/{$rsrId}/{$project->name}.phar"
                ];
            }
            self::$discordQueue[] = [
                "title" => "{$projectType} {$project->name}, {$buildClassName}:{$buildNumber}",
                "url" => $buildPath,
                "timestamp" => $metadata["buildTime"],
                "color" => 0x9B18FF,
                "description" => sprintf('In branch %2$s: https://github.com/%3$s/commit/%1$s', $sha, $branch, $repoData->full_name),
                "fields" => $fields,
                "provider" => [
                    "name" => "Poggit-CI",
                    "url" => "https://poggit.pmmp.io/ci"
                ],
                "author" => [
                    "name" => $triggerUser->login,
                    "url" => "https://github.com/{$triggerUser->login}",
                    "icon_url" => "https://github.com/{$triggerUser->login}.png",
                ],
                "footer" => [
                    "icon_url" => "https://www.iconexperience.com/_img/g_collection_png/standard/512x512/sign_warning.png",
                    "text" => "This is a development build. Don't download it unless you are sure this plugin works!"
                ]
            ];
        }
    }

    public static function flushDiscordQueue() {
        $queue = self::$discordQueue;
        self::$discordQueue = [];
        if(count($queue) > 0) {
            Discord::newBuildsHook(count($queue) > 1 ? sprintf("%d new builds have been created!", count($queue)) : "A new build has been created!", "Poggit-CI", $queue);
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

    protected abstract function build(Phar $phar, RepoZipball $zipball, WebhookProjectModel $project, int $buildId, bool $isRepoPrivate): BuildResult;

    public static function normalizeProjectPath(string $path): string {
        $path = trim($path, "/");
        if($path !== "") {
            $path .= "/";
        }
        while(Lang::startsWith($path, "./")) {
            $path = substr($path, 2);
        }
        $path = str_replace("/./", "/", $path);
        return $path;
    }

    protected function lintManifest(RepoZipball $zipball, BuildResult $result, string &$yaml, string &$mainClass = null): string {
        try {
            $manifest = @yaml_parse($yaml);
        } catch(RuntimeException $e) {
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
        if(count($result->statuses) > 0) {
            return "/dev/null";
        }

        if(!preg_match(/** @lang RegExp */
            '/^(\w+\\\\)*\w+$/', $manifest["main"])) {
            $status = new MalformedClassNameLint();
            $status->className = $manifest["main"];
            $result->addStatus($status);
        }
        /** @var string|string[] $apis */
        $apis = $manifest["api"];
        $usePrefix = false;
        foreach(is_array($apis) ? $apis : [$apis] as $api){
            if($api[0] === "4"){
                $usePrefix = true;
            }
        }
        //Remove src-namespace-prefix from file path if API 4 is listed in API.
        $mainClassFile = $this->project->path . "src/" . ($usePrefix ? str_replace(
            (trim($manifest["src-namespace-prefix"]??"") === "") ? "" : str_replace("\\", "/", $manifest["src-namespace-prefix"])."/",
            "",
            str_replace("\\", "/", $mainClass = $manifest["main"])
        ) : str_replace("\\", "/", $mainClass = $manifest["main"])) . ".php";
        if(!$zipball->isFile($mainClassFile)) {
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

    protected function lintPhpFile(BuildResult $result, string $file, string $contents, bool $isFileMain, string $srcNamespacePrefix = "", $options = []) {
        file_put_contents($this->tempFile, $contents);
        Lang::myShellExec("php -l " . escapeshellarg($this->tempFile), $lint, $stderr, $exitCode);
        $lint = trim(str_replace($this->tempFile, $file, $lint));
        if($exitCode !== 0){
            if($options["syntaxError"] ?? true) {
                $status = new SyntaxErrorLint();
                $status->file = $file;
                $status->output = $lint;
                $result->addStatus($status);
                return;
            }
        }

        if($options === null) return;

        $lines = explode("\n", $contents);
        $tokens = token_get_all($contents);
        $currentLine = 1;
        $namespaces = [];
        $classes = [];
        $wantClass = false;
        $currentNamespace = "";
        $token = null;
        $buildingNamespace = null;
        $hasReportedInlineHtml = null;
        $hasReportedUseOfEcho = null;
        foreach($tokens as $t) {
            if(!is_array($t)) {
                $t = [-1, $t, $currentLine];
            }
            $lastToken = $token ?? [0, "", 0];
            list($tokenId, $currentCode, $currentLine) = $token = $t;
            $currentLine += substr_count($currentCode, "\n");

            if($tokenId === T_WHITESPACE) {
                continue;
            }
            if($tokenId === T_STRING) {
                if(isset($buildingNamespace)) {
                    //Single namespace will be string.
                    $buildingNamespace .= trim($currentCode);
                } elseif($wantClass) {
                    $classes[] = [$currentNamespace, trim($currentCode), $currentLine];
                    $wantClass = false;
                }
            } elseif($tokenId === T_NAME_QUALIFIED) {
                if(isset($buildingNamespace)) {
                    $buildingNamespace = trim($currentCode);
                }
            } elseif($tokenId === T_CLASS) {
                if($lastToken[0] !== T_PAAMAYIM_NEKUDOTAYIM and $lastToken[0] !== T_NEW) {
                    $wantClass = true;
                }
            } elseif($tokenId === T_NAMESPACE) {
                $buildingNamespace = "";
            } elseif($tokenId === -1) {
                if(trim($currentCode) === ";" || trim($currentCode) === "{" and isset($buildingNamespace)) {
                    $namespaces[] = $currentNamespace = $buildingNamespace;
                    unset($buildingNamespace);
                }
            } elseif($tokenId === T_CLOSE_TAG){
                if($options["closeTag"] ?? true) {
                    $status = new CloseTagLint();
                    $status->file = $file;
                    $status->line = $currentLine;
                    $status->code = $lines[$currentLine - 1] ?? "";
                    $status->hlSects[] = [$closeTagPos = strpos($status->code, "\x3F\x3E"), $closeTagPos + 2];
                    $result->addStatus($status);
                }
            } elseif($tokenId === T_INLINE_HTML or $tokenId === T_ECHO) {
                if($tokenId === T_INLINE_HTML) {
                    if(isset($hasReportedInlineHtml)) {
                        continue;
                    }
                    $hasReportedInlineHtml = true;
                }
                if($tokenId === T_ECHO) {
                    if(isset($hasReportedUseOfEcho)) {
                        continue;
                    }
                    $hasReportedUseOfEcho = true;
                }
                if($options["directStdout"] ?? true){
                    $status = new DirectStdoutLint();
                    $status->file = $file;
                    $status->line = $currentLine;
                    if($tokenId === T_INLINE_HTML) {
                        $status->code = $currentCode;
                        $status->isHtml = true;
                    } else {
                        $status->code = $lines[$currentLine - 1] ?? "";
                        $status->hlSects[] = [$hlSectsPos = stripos($status->code, "echo"), $hlSectsPos + 2];
                        $status->isHtml = false;
                    }
                    $status->isFileMain = $isFileMain;
                    $result->addStatus($status);
                }
            }
        }
        //This will need adjusting when PM4 drops with PSR-4 support.
        foreach($classes as list($namespace, $class, $line)) {
            $result->knownClasses[] = $namespace . "\\" . $class;
            //TODO Potentially a PSR-4 lint?
            if($file !== "src/" . str_replace("\\", "/", $namespace) . "/" . $class . ".php" and $srcNamespacePrefix === ""){
                if($options["nonPsr"] ?? true) {
                    $status = new NonPsrLint();
                    $status->file = $file;
                    $status->line = $line;
                    $status->code = $lines[$line - 1] ?? "";
                    //$status->hlSects[] = [$classPos = strpos($status->code, $class), $classPos + 2]; #292
                    $status->class = $namespace . "\\" . $class;
                    $result->addStatus($status);
                }
            }
        }
    }
}
