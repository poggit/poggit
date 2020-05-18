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
use poggit\ci\lint\BuildResult;
use poggit\ci\lint\ManifestAttributeMissingBuildError;
use poggit\ci\lint\ManifestCorruptionBuildError;
use poggit\ci\lint\ManifestMissingBuildError;
use poggit\ci\lint\VirionGenomeBeyondRestrictionWarning;
use poggit\ci\RepoZipball;
use poggit\ci\Virion;
use poggit\Meta;
use poggit\utils\internet\Mysql;
use poggit\utils\lang\Lang;
use poggit\webhook\WebhookProjectModel;
use function count;
use function is_array;
use function json_encode;
use function str_replace;
use function strlen;
use function strtolower;
use function substr;
use const poggit\ASSETS_PATH;

class PoggitVirionBuilder extends ProjectBuilder {
    public function getName(): string {
        return "poggit-virion";
    }

    public function getVersion(): string {
        return "1.0";
    }

    protected function build(Phar $phar, RepoZipball $zipball, WebhookProjectModel $project, int $buildId, bool $isRepoPrivate): BuildResult {
        $this->project = $project;
        $this->tempFile = Meta::getTmpFile(".php");
        $result = new BuildResult();
        $phar->startBuffering();
        $phar->setStub('<?php require "phar://" . __FILE__ . "/virion_stub.php"; __HALT_COMPILER();');
        $phar->addFile(ASSETS_PATH . "php/virion.php", "virion.php");
        $phar->addFile(ASSETS_PATH . "php/virion_stub.php", "virion_stub.php");
        $manifestPath = $project->path . "virion.yml";
        if(!$zipball->isFile($manifestPath)) {
            $status = new ManifestMissingBuildError();
            $status->manifestName = "virion.yml";
            $result->addStatus($status);
            return $result;
        }
        $manifestData = yaml_parse($zipball->getContents($manifestPath));
        if(!is_array($manifestData)) {
            $status = new ManifestCorruptionBuildError();
            $status->manifestName = "virion.yml";
            $result->addStatus($status);
            return $result;
        }
        foreach(["name", "version", "antigen"] as $attr) {
            if(!isset($manifestData[$attr])) {
                $error = new ManifestAttributeMissingBuildError();
                $error->attribute = $attr;
                $result->addStatus($error);
            }
        }
        $result->main = $manifestData["antigen"];
        if(!isset($manifestData["api"]) && !isset($manifestData["php"])) {
            $error = new ManifestAttributeMissingBuildError();
            $error->attribute = "api|php";
            $result->addStatus($error);
        }
        if(count($result->statuses) > 0) return $result;
        $manifestData["build"] = $phar->getMetadata();
        $phar->addFromString("virion.yml", yaml_emit($manifestData));

        if($zipball->isFile($project->path . "cli-map.json")) {
            $phar->addFromString("cli-map.json", $zipball->getContents($project->path . "cli-map.json"));
            $phar->addFile(ASSETS_PATH . "php/cli-autoload.php", "cli-autoload.php");
        }

        $restriction = $project->path . "src/" . str_replace("\\", "/", $manifestData["antigen"]) . "/";
        foreach($zipball->iterator("", true) as $file => $reader) {
            if(!Lang::startsWith($file, $project->path)) continue;
            if(substr($file, -1) === "/") continue;
            if(Lang::startsWith($file, $project->path . "cli/") or Lang::startsWith($file, $project->path . "resources/") or Lang::startsWith($file, $project->path . "src/")) {
                if(Lang::startsWith($file, $project->path . "src/") and !Lang::startsWith($file, $restriction)) {
                    $status = new VirionGenomeBeyondRestrictionWarning();
                    $status->antigen = $manifestData["antigen"];
                    $status->genome = $file;
                    $result->addStatus($status);
                }
                $phar->addFromString($localName = substr($file, strlen($project->path)), $contents = $reader());
                if(Lang::startsWith($localName, "src/") and Lang::endsWith(strtolower($localName), ".php")) {
                    $this->lintPhpFile($result, $localName, $contents, false);
                }
            }
        }
        Virion::processLibs($phar, $zipball, $project, function() use ($manifestData) {
            return $manifestData["antigen"] . "\\";
        });
        if($phar->getMetadata()["buildClass"] !== "PR") {
            Mysql::query("INSERT INTO virion_builds (buildId, version, api) VALUES (?, ?, ?)", "iss",
                $phar->getMetadata()["poggitBuildId"], $manifestData["version"], json_encode(isset($manifestData["api"]) ? ((array) $manifestData["api"]) : "*"));
        }
        return $result;
    }
}
