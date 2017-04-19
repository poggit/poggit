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
use poggit\ci\lint\BuildResult;
use poggit\ci\lint\ManifestMissingBuildError;
use poggit\ci\lint\PromisedStubMissingLint;
use poggit\ci\RepoZipball;
use poggit\Poggit;
use poggit\utils\lang\LangUtils;
use poggit\webhook\WebhookProjectModel;

class DefaultProjectBuilder extends ProjectBuilder {
    public function getName(): string {
        return "default";
    }

    public function getVersion(): string {
        return "2.0";
    }

    protected function build(Phar $phar, RepoZipball $zipball, WebhookProjectModel $project): BuildResult {
        $this->project = $project;
        $this->tempFile = Poggit::getTmpFile(".php");
        $result = new BuildResult();
        $path = $project->path;
        if(isset($project->manifest["stub"])) {
            $stubFile = $project->manifest["stub"];
            $stubPath = $stubFile{0} === "/" ? substr($stubFile, 1) : $project->path . $stubFile;
            if($zipball->isFile($stubPath)) {
                $phar->addFromString("stub.php", $zipball->getContents($stubPath));
                $phar->setStub('<?php include "phar://" . __FILE__ . "/stub.php"; __HALT_COMPILER();');
            } else {
                $status = new PromisedStubMissingLint;
                $status->stubName = $stubPath;
                $result->addStatus($status);
                $phar->setStub('<?php __HALT_COMPILER();');
            }
        } else {
            $phar->setStub('<?php __HALT_COMPILER();');
        }
        if(!$zipball->isFile($path . "plugin.yml")) {
            echo "Cannot find {$path}plugin.yml in file\n";
            $status = new ManifestMissingBuildError();
            $status->manifestName = $path . "plugin.yml";
            $result->addStatus($status);
            return $result;
        }
        $manifest = $zipball->getContents($path . "plugin.yml");
        $mainClassFile = $this->lintManifest($zipball, $result, $manifest, $mainClass);
        $phar->addFromString("plugin.yml", $manifest);
        if($result->worstLevel === BuildResult::LEVEL_BUILD_ERROR) return $result;

        foreach($zipball->iterator("", true) as $file => $reader) {
            if(!LangUtils::startsWith($file, $project->path)) continue;
            if(substr($file, -1) === "/") continue;
            if(LangUtils::startsWith($file, $project->path . "resources/") or LangUtils::startsWith($file, $project->path . "src/")) {
                $phar->addFromString($localName = substr($file, strlen($project->path)), $contents = $reader());
                if(LangUtils::startsWith($localName, "src/") and LangUtils::endsWith(strtolower($localName), ".php")) {
                    $this->lintPhpFile($result, $localName, $contents, $localName === $mainClassFile);
                }
            }
        }

        $this->processLibs($phar, $zipball, $project, function () use ($mainClass) {
            return implode("\\", array_slice(explode("\\", $mainClass), 0, -1)) . "\\";
        });

        return $result;
    }
}
