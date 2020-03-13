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
use poggit\ci\lint\ManifestMissingBuildError;
use poggit\ci\lint\PhpstanInternalError;
use poggit\ci\lint\PhpstanLint;
use poggit\ci\lint\PromisedStubMissingLint;
use poggit\ci\RepoZipball;
use poggit\ci\Virion;
use poggit\Meta;
use poggit\utils\lang\Lang;
use poggit\utils\lang\NativeError;
use poggit\webhook\WebhookProjectModel;
use function array_slice;
use function explode;
use function implode;
use function in_array;
use function strlen;
use function strtolower;
use function substr;
use function trim;
use function var_export;

class DefaultProjectBuilder extends ProjectBuilder {
    public function getName(): string {
        return "default";
    }

    public function getVersion(): string {
        return "2.0";
    }

    protected function build(Phar $phar, RepoZipball $zipball, WebhookProjectModel $project): BuildResult {
        $this->project = $project;
        $this->tempFile = Meta::getTmpFile(".php");
        $result = new BuildResult();
        $path = $project->path;
        $phar->addFromString(".poggit", "");
        if(isset($project->manifest["stub"])) {
            $stubFile = $project->manifest["stub"];
            if($stubFile{0} === "/") { // absolute
                $stubPath = substr($stubFile, 1);
                if($zipball->isFile($stubPath)) {
                    $phar->addFromString("stub.php", $zipball->getContents($stubPath));
                    $phar->setStub(/** @lang PHP */
                        '<?php require "phar://" . __FILE__ . "/stub.php"; __HALT_COMPILER();');
                } else {
                    $badStub = true;
                }
            } else {
                $stubPath = $project->path . $stubFile;
                if($zipball->isFile($stubPath)) {
                    $phar->addFromString($stubPath, $zipball->getContents($stubPath));
                    $phar->setStub(/** @lang PHP */
                        '<?php require "phar://" . __FILE__ . "/" . ' . var_export($stubPath, true) . '; __HALT_COMPILER();');
                } else {
                    $badStub = true;
                }
            }
            if(isset($badStub)) {
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
        $pluginYml = $zipball->getContents($path . "plugin.yml");
        $mainClassFile = $this->lintManifest($zipball, $result, $pluginYml, $mainClass);
        $phar->addFromString("plugin.yml", $pluginYml);
        try {
            $result->main = yaml_parse($pluginYml)["main"] ?? "Invalid plugin.yml in build";
        } catch(NativeError $e) {
            $result->main = "(Invalid plugin.yml in build)";
        }

        if($result->worstLevel === BuildResult::LEVEL_BUILD_ERROR) return $result;

        $filesToAdd = [];
        $dirsToAdd = [$project->path . "resources/" => "resources/", $project->path . "src/" => "src/"];
        if(isset($project->manifest["includeDirs"])) {
            foreach($project->manifest["includeDirs"] as $repoPath => $pharPath) {
                $dirsToAdd[trim($repoPath{0} === "/" ? substr($repoPath, 1) : $project->path . $repoPath, "/") . "/"]
                    = trim($pharPath === "=" ? $repoPath : $pharPath, "/") . "/";
            }
        }
        if(isset($project->manifest["includeFiles"])) {
            foreach((array) $project->manifest["includeFiles"] as $repoPath => $pharPath) {
                $filesToAdd[$repoPath{0} === "/" ? substr($repoPath, 1) : ($project->path . $repoPath)] = $pharPath;
            }
        }
        $filesToExclude = [];
        $dirsToExclude = [];
        if(isset($project->manifest["excludeFiles"])) {
            foreach((array) $project->manifest["excludeFiles"] as $file) {
                $filesToExclude[] = $file{0} === "/" ? substr($file, 1) : ($project->path . $file);
            }
        }
        if(isset($project->manifest["excludeDirs"])) {
            foreach((array) $project->manifest["excludeDirs"] as $dir) {
                $dirsToExclude[] = trim($dir{0} === "/" ? substr($dir, 1) : ($project->path . $dir), "/") . "/";
            }
        }

        // zipball_loop:
        foreach($zipball->iterator("", true) as $file => $reader) {
            // skip dirs
            if(substr($file, -1) === "/") continue;

            // check includeFiles
            if(isset($filesToAdd[$file])) {
                $inOut = [$file, $filesToAdd[$file]];
            } else {
                // check includeDirs
                // dir_loop:
                unset($inOut);
                foreach($dirsToAdd as $in => $out) {
                    if(Lang::startsWith($file, $in)) {
                        $inOut = [$in, $out];
                        break; // dir_loop
                    }
                }
                if(!isset($inOut)) continue;

                // check excludeFiles
                if(in_array($file, $filesToExclude, true)) continue;

                // check excludeDirs
                foreach($dirsToExclude as $dir) {
                    if(Lang::startsWith($file, $dir)) {
                        continue 2; // zipball_loop
                    }
                }
            }

            list($in, $out) = $inOut;
            $localName = $out . substr($file, strlen($in));
            $phar->addFromString($localName, $contents = $reader());
            if(Lang::startsWith($localName, "src/") and Lang::endsWith(strtolower($localName), ".php")) {
                $this->lintPhpFile($result, $localName, $contents, $localName === $mainClassFile, $project->manifest["lint"] ?? true);
            }
        }

        Virion::processLibs($phar, $zipball, $project, function() use ($mainClass) {
            return implode("\\", array_slice(explode("\\", $mainClass), 0, -1)) . "\\";
        });

        if($project->manifest["phpstan"] ?? true){
            //if($result->worstLevel <= BuildResult::LEVEL_LINT) {
            $phar->stopBuffering();
            $this->runPhpstan($phar->getPath(), $result);
            $phar->startBuffering();
            /*} else {
                echo "PHPStan cancelled, errors found before analysis.\n";
                Meta::getLog()->i("Not running PHPStan for ".$this->project->name." as errors have already been identified.");
            }*/
        }

        return $result;
    }

    protected function runPhpstan(string $phar, BuildResult $result){
        $id = "phpstan-".(Meta::getRequestId() ?? bin2hex(random_bytes(8)));

        Meta::getLog()->v("Starting PHPStan flow with ID '{$id}'");

        Lang::myShellExec("docker create --name {$id} jaxkdev/poggit-phpstan:0.0.5", $stdout, $stderr, $exitCode);

        if($exitCode !== 0){
            $status = new PhpstanInternalError();
            $status->exception = "PHPStan failed with ID '{$id}', contact support with the ID for help if this persists.";
            $result->addStatus($status);
            Meta::getLog()->e("Failed to create docker container with id '{$id}', Status: {$exitCode}, stderr: {$stderr}");
            return;
        }

        Meta::getLog()->v("Copying plugin '{$phar}' into '{$id}:/source/plugin.phar'");

        Lang::myShellExec("docker cp {$phar} {$id}:/source/plugin.phar", $stdout, $stderr, $exitCode);

        if($exitCode !== 0){
            $status = new PhpstanInternalError();
            $status->exception = "PHPStan failed with ID '{$id}', contact support with the ID for help if this persists.";
            $result->addStatus($status);
            Meta::getLog()->e("Failed to copy '{$phar}' into '{$id}:/source/plugin.phar', Status: {$exitCode}, stderr: {$stderr}");
            return;
        }

        Meta::getLog()->v("Starting container '{$id}'");

        Lang::myShellExec("docker start -ia {$id}", $stdout, $stderr, $exitCode);

        if($exitCode !== 0 and $stderr === ""){ //Exits with 1 if problems found...
            $status = new PhpstanInternalError();
            $status->exception = "PHPStan failed with ID '{$id}', contact support with the ID for help if this persists.";
            $result->addStatus($status);
            Meta::getLog()->e("Failed to start container '{$id}', Status: {$exitCode}, stderr: {$stderr}");
            return;
        }

        if($exitCode === 255 and substr($stderr,0,11) === "Parse error"){
            $status = new PhpstanLint();
            $status->message = str_replace("/source/", "", $stderr);
            $result->addStatus($status);
            return;
        }

        $tmpFile = Meta::getTmpFile(".json"); //Extension is f*****

        Meta::getLog()->v("Copying results from container '{$id}' into temp file '{$tmpFile}'");

        Lang::myShellExec("docker cp {$id}:/source/phpstan-results.json {$tmpFile}", $stdout, $stderr, $exitCode);

        if($exitCode !== 0){
            $status = new PhpstanInternalError();
            $status->exception = "PHPStan failed with ID '{$id}', contact support with the ID for help if this persists.";
            $result->addStatus($status);
            Meta::getLog()->e("Failed to copy results from container '{$id}', Status: {$exitCode}, stderr: {$stderr}");
            return;
        }

        $data = json_decode(file_get_contents($tmpFile), true);

        //@unlink($tmpFile); TODO uncomment, debug purposes.

        if($data === null){
            $status = new PhpstanInternalError();
            $status->exception = "PHPStan results are corrupt - ID '{$id}', contact support with the ID for help if this persists.";
            $result->addStatus($status);
            Meta::getLog()->e("Failed to decode results from container '{$id}', Status: {$exitCode}, stderr: {$stderr}");
            return;
        }

        if(!isset($data["totals"])){
            $status = new PhpstanInternalError();
            $status->exception = "PHPStan results are corrupt - ID '{$id}', contact support with the ID for help if this persists.";
            $result->addStatus($status);
            Meta::getLog()->e("Failed to decode results from container '{$id}', Status: {$exitCode}, stderr: {$stderr}");
            return;
        }

        Meta::getLog()->v("PHPStan OK, removing container '{$id}'");

        Lang::myShellExec("docker container rm {$id}", $stdout, $stderr, $exitCode);

        if($exitCode !== 0){
            $status = new PhpstanInternalError();
            $status->exception = "Internal error occurred, ID '{$id}', contact support with the ID for help if this persists.";
            $result->addStatus($status);
            Meta::getLog()->e("Failed to remove container '{$id}', Status: {$exitCode}, stderr: {$stderr}");
            return;
        }

        //TODO Parse & store results.
    }
}
