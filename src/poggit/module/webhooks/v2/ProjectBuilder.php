<?php

/*
 * Poggit
 *
 * Copyright (C) 2016 Poggit
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

namespace poggit\module\webhooks\v2;

use Phar;
use poggit\module\webhooks\RepoZipball;
use poggit\module\webhooks\v2\cause\V2BuildCause;
use poggit\module\webhooks\v2\lint\BuildResult;
use poggit\module\webhooks\v2\lint\InternalBuildError;
use poggit\Poggit;
use poggit\resource\ResourceManager;
use stdClass;

abstract class ProjectBuilder {
    static $IMPL = [
        "default" => DefaultProjectBuilder::class,
        "nowhere" => NowHereProjectBuilder::class,
    ];

    /**
     * @param RepoZipball           $zipball
     * @param stdClass              $repoData
     * @param WebhookProjectModel[] $projects
     * @param string[]              $commitMessages
     * @param string[]              $changedFiles
     * @param V2BuildCause          $cause
     * @param callable              $buildNumber
     * @param int                   $buildClass
     * @param string                $branch
     * @param string                $sha
     */
    public static function buildProjects(RepoZipball $zipball, stdClass $repoData, array $projects, array $commitMessages, array $changedFiles,
                                         V2BuildCause $cause, callable $buildNumber, int $buildClass, string $branch, string $sha) {
        $needBuild = [];
        foreach($projects as $project) {
            if($project->devBuilds === 0) {
                $needBuild[] = $project;
                continue;
            }
            foreach($commitMessages as $message) {
                if(preg_match_all('/poggit[:,] (please )?build ([a-z0-9\-_., ]+)/i', $message, $matches, PREG_SET_ORDER)) { // TODO optimization
                    foreach($matches[2] as $match) {
                        foreach(explode(",", $match) as $name) {
                            $name = strtolower(trim($name));
                            if($name === "all" or $name === strtolower($project->name)) {
                                $needBuild[] = $project;
                                continue 4; // WTF
                            }
                        }
                    }
                }
            }
            foreach($changedFiles as $fileName) {
                if($fileName === ".poggit/.poggit.yml" or $fileName === ".poggit.yml" or Poggit::startsWith($fileName, $project->path)) {
                    $needBuild[] = $project;
                    continue 2;
                }
            }
        }
        foreach($needBuild as $project) {
            Poggit::ghApiPost("repos/{$repoData->owner->name}/{$repoData->name}/statuses/$sha", [
                "state" => "pending",
                "description" => "Build in progress",
                "context" => "$project->name Poggit Build"
            ], RepoWebhookHandler::$token);
        }
        foreach($needBuild as $project) {
            $modelName = $project->framework;
            $class = self::$IMPL[strtolower($modelName)];
            /** @var ProjectBuilder $builder */
            $builder = new $class();
            $builder->init($zipball, $repoData, $project, $cause, $buildNumber, $buildClass, $branch, $sha);
        }
    }

    public function init(RepoZipball $zipball, stdClass $repoData, WebhookProjectModel $project, V2BuildCause $cause, callable $buildNumberGetter,
                         int $buildClass, string $branch, string $sha) {
        $buildId = (int) Poggit::queryAndFetch("SELECT IFNULL(MAX(buildId), 0x4B00) + 1 AS nextBuildId FROM builds")[0]["nextBuildId"];
        Poggit::queryAndFetch("INSERT INTO builds (buildId, projectId) VALUES (?, ?)", "ii", $buildId, $project->projectId);
        $buildNumber = $buildNumberGetter($project);

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
        $file = ResourceManager::getInstance()->createResource("phar", "application/octet-stream", $accessFilters, $rsrId);

        $phar = new Phar($file);
        $phar->setSignatureAlgorithm(Phar::SHA1);
        $metadata = [
            "builder" => "Poggit/" . Poggit::POGGIT_VERSION . " " . $this->getName() . "/" . $this->getVersion(),
            "buildTime" => date(DATE_ISO8601),
            "poggitBuildId" => $buildId,
            "projectBuildNumber" => $buildNumber,
            "class" => $buildClassName = Poggit::$BUILD_CLASS_HUMAN[$buildClass]
        ];
        $phar->setMetadata($metadata);

        try {
            $buildResult = $this->build($phar, $zipball, $project);
        } catch(\Throwable $e) {
            $buildResult = new BuildResult();
            $buildResult->worstLevel = BuildResult::LEVEL_BUILD_ERROR;
            $buildResult->statuses = [new InternalBuildError()];
        }

        Poggit::queryAndFetch("UPDATE builds SET resourceId = ?, class = ?, branch = ?, cause = ?, internal = ?, status = ? WHERE buildId = ?",
            "iissisi", $rsrId, $buildClass, $branch, json_encode($cause, JSON_UNESCAPED_SLASHES), $buildNumber,
            json_encode($buildResult->statuses, JSON_UNESCAPED_SLASHES), $buildId);
        Poggit::ghApiPost("repos/{$repoData->owner->name}/{$repoData->name}/statuses/$sha", [
            "state" => BuildResult::$states[$buildResult->worstLevel],
            "target_url" => Poggit::getSecret("meta.extPath") . "babs/" . $buildId,
            "description" => "Created $buildClassName build #$buildNumber (&$buildId)",
            "context" => "$project->name Poggit Build"
        ], RepoWebhookHandler::$token);
    }

    public abstract function getName() : string;

    public abstract function getVersion() : string;

    protected abstract function build(Phar $phar, RepoZipball $zipball, WebhookProjectModel $project) : BuildResult;
}
