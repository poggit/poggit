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
use poggit\builder\lint\BuildResult;
use poggit\builder\lint\MainClassMissingLint;
use poggit\builder\lint\MalformedClassNameLint;
use poggit\builder\lint\ManifestAttributeMissingBuildError;
use poggit\builder\lint\ManifestCorruptionBuildError;
use poggit\builder\lint\ManifestMissingBuildError;
use poggit\builder\lint\PluginNameTransformedLint;
use poggit\builder\lint\PromisedStubMissingLint;
use poggit\builder\lint\RestrictedPluginNameLint;
use poggit\builder\lint\SyntaxErrorLint;
use poggit\module\webhooks\repo\WebhookProjectModel;
use poggit\Poggit;
use poggit\utils\lang\LangUtils;

class DefaultProjectBuilder extends ProjectBuilder {
    private $project;
    private $tempFile;

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
        $mainClassFile = $this->lintManifest($zipball, $result, $manifest);
        $phar->addFromString("plugin.yml", $manifest);
        if($result->worstLevel === BuildResult::LEVEL_BUILD_ERROR) return $result;

        foreach($zipball->callbackIterator() as $file => $reader) {
            if(!LangUtils::startsWith($file, $project->path)) continue;
            if(substr($file, -1) === "/") continue;
            if(LangUtils::startsWith($file, $project->path . "resources/") or LangUtils::startsWith($file, $project->path . "src/")) {
                $phar->addFromString($localName = substr($file, strlen($project->path)), $contents = $reader());
                if(LangUtils::endsWith(strtolower($localName), ".php")) {
                    $this->lintPhpFile($result, $localName, $contents, $localName === $mainClassFile);
                }
            }
        }

        return $result;
    }

    private function lintManifest(RepoZipball $zipball, BuildResult $result, string &$yaml): string {
        try {
            $manifest = @yaml_parse($yaml);
        } catch(\RuntimeException $e) {
            $manifest = false;
        }
        if(!is_array($manifest)) {
            $error = new ManifestCorruptionBuildError();
            $error->manifestName = "plugin.yml";
            // TODO handle parse errors?
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
        if(!$zipball->isFile($mainClassFile = $this->project->path . "src/" . str_replace("\\", "/", $manifest["main"]) . ".php")) {
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

    private function lintPhpFile(BuildResult $result, string $file, string $contents, bool $isFileMain) {
        file_put_contents($this->tempFile, $contents);
        LangUtils::myShellExec("php -l " . escapeshellarg($this->tempFile), $stdout, $lint, $exitCode);
        if($exitCode !== 0) {
            $status = new SyntaxErrorLint();
            $status->file = $file;
            $status->output = $lint;
            $result->addStatus($status);
            return;
        }

        $this->checkPhp($result, $file, $contents, $isFileMain);
    }
}
