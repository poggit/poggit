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

use poggit\module\webhooks\RepoZipball;
use poggit\Poggit;

abstract class ProjectBuilder {
    static $IMPL = [
        "default" => DefaultProjectBuilder::class,
        "nowhere" => NowHereProjectBuilder::class,
    ];

    /**
     * @param RepoZipball           $zipball
     * @param \stdClass             $repoData
     * @param WebhookProjectModel[] $projects
     * @param string[]              $commitMessages
     * @param string[]              $changedFiles
     */
    public static function buildProjects(RepoZipball $zipball, \stdClass $repoData, array $projects, array $commitMessages, array $changedFiles) {
        foreach($projects as $project) {
            if($project->devBuilds === 0) goto do_build;
            foreach($commitMessages as $message) {
                if(preg_match_all('/poggit[:,] (please )?build ([a-z0-9\-_., ]+)/i', $message, $matches, PREG_SET_ORDER)) { // TODO optimization
                    foreach($matches[2] as $match) {
                        foreach(explode(",", $match) as $name) {
                            if(strtolower($name) === "all" or strtolower($name) === strtolower($project->name)) goto do_build;
                        }
                    }
                }
            }
            foreach($changedFiles as $fileName) {
                if($fileName === ".poggit/.poggit.yml" or $fileName === ".poggit.yml" or Poggit::startsWith($fileName, $project->path)) goto do_build;
            }
            continue;

            do_build:
            $modelName = $project->framework;
            $class = self::$IMPL[strtolower($modelName)];
            /** @var ProjectBuilder $builder */
            $builder = new $class();
            $builder->init($zipball, $repoData, $project);
        }
    }

    public function init(RepoZipball $zipball, \stdClass $repoData, WebhookProjectModel $project) {
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
    }
}
