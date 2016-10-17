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

namespace poggit\module\webhooks;

use poggit\model\BuildThumbnail;
use poggit\model\ProjectThumbnail;
use poggit\module\webhooks\buildcause\BuildCause;
use poggit\module\webhooks\buildstatus\ExceptionBuildStatus;
use poggit\module\webhooks\framework\FrameworkBuilder;
use poggit\module\webhooks\framework\ProjectBuildException;
use poggit\Poggit;
use poggit\resource\ResourceManager;

abstract class BuildingWebhookHandler extends WebhookHandler {
    private $buildSuccessful;
    private $statuses;

    /**
     * @param RepoZipball      $zipball
     * @param ProjectThumbnail $project
     * @param array            $accessFilters
     * @param BuildCause       $buildCause
     * @param callable         $nextGlobalBuildId
     * @return BuildThumbnail|null
     */
    public function projectBuild(RepoZipball $zipball, ProjectThumbnail $project, array $accessFilters, BuildCause $buildCause, callable $nextGlobalBuildId) {
        echo "Building project: $project->name\n";
        $model = strtolower($project->framework);
        if(!isset(FrameworkBuilder::$builders[$model])) {
            echo "    Failed: model $project->framework not supported\n";
            return null;
        }
        $builder = FrameworkBuilder::$builders[$model];
        $artifactFile = ResourceManager::getInstance()->createResource("phar", "application/octet-stream", $accessFilters, $rsrId);
        $phar = new \Phar($artifactFile);
        $phar->startBuffering();
        $phar->setSignatureAlgorithm(\Phar::SHA1);
        $metadata = [
            "builder" => "Poggit/" . Poggit::POGGIT_VERSION . " " .
                $builder->getName() . "/" . $builder->getVersion(),
            "buildTime" => date(DATE_ISO8601),
            "poggitBuildId" => $buildId = $nextGlobalBuildId(),
            "projectBuildId" => $internalId = ++$project->latestBuildInternalId,
            "class" => Poggit::$BUILD_CLASS_HUMAN[Poggit::BUILD_CLASS_DEV],
        ];

        $build = new BuildThumbnail();
        $build->globalId = $buildId;
        $build->internalId = $internalId;
        $build->projectName = $project->name;
        $build->created = round(microtime(true), 3);
        try {
            $filesForLint = $builder->build($zipball, $project, $phar);
        } catch(ProjectBuildException $ex) {
            $status = new ExceptionBuildStatus();
            $status->message = $ex->getMessage();
            $this->buildSuccessful = false;
            $this->statuses = [$status];
            return null;
        }


        $this->statuses = $builder->lint($filesForLint);
        $this->buildSuccessful = true;
        return $build;
    }

    public function isBuildSuccessful() : bool {
        return $this->buildSuccessful;
    }

    public function getStatuses() : array {
        $s = $this->statuses;
        $this->statuses = [];
        return $s;
    }
}
