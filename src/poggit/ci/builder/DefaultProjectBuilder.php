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
use poggit\Config;
use poggit\Meta;
use poggit\resource\ResourceManager;
use poggit\utils\internet\Mysql;
use poggit\utils\lang\Lang;
use poggit\utils\lang\NativeError;
use poggit\webhook\WebhookHandler;
use poggit\webhook\WebhookProjectModel;
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

        if(($project->manifest["lint"] ?? true) === true) $project->manifest["lint"] = [];
        $doLint = is_array($project->manifest["lint"]) ? true : $project->manifest["lint"]; //Old config format.

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
                $this->lintPhpFile($result, $localName, $contents, $localName === $mainClassFile, ($doLint ? $project->manifest["lint"] : null));
            }
        }

        Virion::processLibs($phar, $zipball, $project, function() use ($mainClass) {
            return implode("\\", array_slice(explode("\\", $mainClass), 0, -1)) . "\\";
        });

        if(!($doLint)){
            echo "Lint & PHPStan skipped.\n";
            return $result;
        }

        if(($project->manifest["lint"]["phpstan"] ?? true)){
            if($result->worstLevel <= BuildResult::LEVEL_LINT) {
                $this->runPhpstan($zipball, $result, $project);
            } else {
                echo "PHPStan cancelled, Build was not OK before analysis.\n";
                Meta::getLog()->i("Not running PHPStan for ".$this->project->name." as poggit has already identified problems.");
            }
        }

        return $result;
    }

    private function runPhpstan(RepoZipball $zipball, BuildResult $result, WebhookProjectModel $project){
        $id = "phpstan-" . substr(Meta::getRequestId() ?? bin2hex(random_bytes(8)), 0, 4) . "-" . bin2hex(random_bytes(4));

        Meta::getLog()->v("Starting Pre-PHPStan flow with ID '{$id}'");

        // Get virion dependency's:

        $virions = []; //[Name => ResourcePath]
        $libs = $project->manifest["libs"] ?? null;
        if(is_array($libs)){
            foreach($libs as $lib) {
                if(!isset($lib["src"])) continue; //Poggit will pick this up when it injects virions no need to do anything here.
                $srcParts = Lang::explodeNoEmpty("/", trim($lib["src"], " \t\n\r\0\x0B/"));
                if(count($srcParts) === 0) continue;
                $virionName = array_pop($srcParts);
                $virionRepo = array_pop($srcParts) ?? $project->repo[1];
                $virionOwner = array_pop($srcParts) ?? $project->repo[0];

                $version = $lib["version"] ?? "*";
                $branch = $lib["branch"] ?? ":default";

                try{
                    $virion = Virion::findVirion("$virionOwner/$virionRepo", $virionName, $version, function($apis) {
                        return true;
                    }, WebhookHandler::$token, WebhookHandler::$user, $branch);
                    $path = ResourceManager::pathTo($virion->resourceId, "phar");
                    $virions[$virionName] = $path;
                } catch (UserFriendlyException $e) {}
            }
        }

        //Get plugin dependency's:

        $pluginDep = []; //[Name => ResourcePath]
        $pluginDepNames = [];
        $pluginYaml = $zipball->getContents($project->path."plugin.yml");
        if($pluginYaml !== false){
            $pluginYaml = yaml_parse($pluginYaml);
            if($pluginYaml !== false){
                if(!is_array($pluginYaml["depend"] ?? [])) $pluginYaml["depend"] = [$pluginYaml["depend"]]; //One declared plugin non array style.
                if(!is_array($pluginYaml["softdepend"] ?? [])) $pluginYaml["softdepend"] = [$pluginYaml["softdepend"]]; //One declared plugin non array style.
                $pluginDepNames = array_merge(($pluginYaml["depend"] ?? []), ($pluginYaml["softdepend"] ?? []));
            }
        }

        foreach($pluginDepNames as $name) {
            $check = Mysql::query("SELECT projectId FROM releases WHERE name = ? AND state >= ? LIMIT 1", "si", $name, Config::MIN_PUBLIC_RELEASE_STATE);
            if(count($check) > 0) {
                $projectId = (int) $check[0]["projectId"];
                $rows = Mysql::query("SELECT resourceId FROM builds
                    WHERE projectId = ? AND class = ?
                    ORDER BY buildId DESC LIMIT 1", "ii", $projectId, ProjectBuilder::BUILD_CLASS_DEV);
                if(count($rows) > 0) {
                    $resourceId = (int) $rows[0]["resourceId"];
                    $pluginDep[$name] = ResourceManager::pathTo($resourceId, "phar");
                }
            }
        }

        try {
            $pluginPath = "/".trim($project->path,"/");
            $pluginInternalPath = $zipball->getZipPath();

            Lang::myShellExec("docker create -e PLUGIN_PATH={$pluginPath} --name {$id} jaxkdev/poggit-phpstan:0.2.0", $stdout, $stderr, $exitCode);

            if($exitCode !== 0) {
                $status = new PhpstanInternalError();
                $status->exception = "PHPStan failed with ID '{$id}', contact support with the ID for help if this persists.";
                $result->addStatus($status);
                Meta::getLog()->e("Failed to create docker container with id '{$id}', Status: {$exitCode}, stderr: {$stderr}");
                return;
            }

            Meta::getLog()->v("Copying plugin from '{$pluginInternalPath}' into '{$id}:/source/plugin.zip'");

            Lang::myShellExec("docker cp {$pluginInternalPath} {$id}:/source/plugin.zip", $stdout, $stderr, $exitCode);

            if($exitCode !== 0) {
                $status = new PhpstanInternalError();
                $status->exception = "PHPStan failed with ID '{$id}', contact support with the ID for help if this persists.";
                $result->addStatus($status);
                Meta::getLog()->e("Failed to copy plugin from '{$pluginInternalPath}' into '{$id}:/source/plugin.zip', Status: {$exitCode}, stderr: {$stderr}");
                return;
            }

            //Dependency's:
            $depList = array_merge($virions, $pluginDep);
            foreach(array_keys($depList) as $depName){
                $depPath = $depList[$depName];
                Meta::getLog()->v("Copying dependency '{$depName}' from '{$depPath}' into '{$id}:/deps'");

                Lang::myShellExec("docker cp {$depPath} {$id}:/deps", $stdout, $stderr, $exitCode);

                if($exitCode !== 0) {
                    $status = new PhpstanInternalError();
                    $status->exception = "PHPStan failed with ID '{$id}', contact support with the ID for help if this persists.";
                    $result->addStatus($status);
                    Meta::getLog()->e("Failed to copy dependency '{$depName}' from '{$depPath}' into '{$id}:/deps', Status: {$exitCode}, stderr: {$stderr}");
                    return;
                }
            }


            Meta::getLog()->v("Starting container '{$id}'");

            Lang::myShellExec("docker start -ia {$id}", $stdout, $stderr, $exitCode);

            if($exitCode !== 0 and ($exitCode < 3 or $exitCode > 8)) {
                $status = new PhpstanInternalError();
                $status->exception = "PHPStan failed with ID '{$id}', contact support with the ID for help if this persists.";
                $result->addStatus($status);
                Meta::getLog()->e("Failed to start PHPStan, Unknown exit code from container '{$id}', Code: {$exitCode}");
                return;
            }

            switch($exitCode) {
                case 6:
                case 0:
                    break;

                case 3:
                    Meta::getLog()->e("Failed to extract plugin, see log in container '{$id}' for more information.");

                case 4:
                    Meta::getLog()->e("Failed to extract dependency's, see log in container '{$id}' for more information.");
                    $status = new PhpstanInternalError();
                    $status->exception = "PHPStan failed with ID '{$id}', contact support with the ID for help if this persists.";
                    $result->addStatus($status);
                    return;

                case 5:
                    Meta::getLog()->e("Composer failed to install dependencies, see log in container '{$id}' for more information.");
                    $status = new PhpstanInternalError();
                    $status->exception = "PHPStan failed with ID '{$id}', Composer failed to install dependencies. (If your composer.json file is accurate contact support with the ID to get some assistance";
                    $result->addStatus($status);
                    return;

                case 7:
                    if(substr($stderr, 0, 11) === "Parse error" || substr($stderr, 0, 11) === "Fatal error") {
                        $status = new PhpstanLint();
                        $status->message = str_replace("/source/", "", $stderr);
                        $result->addStatus($status);
                    } else {
                        Meta::getLog()->e("Unknown problem (exit code: 7 (original 255)), see log in container '{$id}' for more information.");
                        $status = new PhpstanInternalError();
                        $status->exception = "PHPStan failed with ID '{$id}', contact support with the ID for help if this persists.";
                        $result->addStatus($status);
                    }
                    return;

                case 8:
                    $problems = $stderr;
                    $status = new PhpstanInternalError();
                    $status->exception = "PHPStan failed to run analysis (ID: {$id}) due to the following:  {$problems}";
                    $result->addStatus($status);
                    return;
            }

            if($exitCode !== 0) {
                $tmpFile = Meta::getTmpFile(".json"); //Extension is actually 2 char prefix.

                Meta::getLog()->v("Copying results from container '{$id}' into temp file '{$tmpFile}'");

                Lang::myShellExec("docker cp {$id}:/source/phpstan-results.json {$tmpFile}", $stdout, $stderr, $exitCode);

                if($exitCode !== 0) {
                    $status = new PhpstanInternalError();
                    $status->exception = "PHPStan failed with ID '{$id}', contact support with the ID for help if this persists.";
                    $result->addStatus($status);
                    Meta::getLog()->e("Failed to copy results from container '{$id}', Status: {$exitCode}, stderr: {$stderr}");
                    return;
                }

                $data = json_decode(file_get_contents($tmpFile), true);

                if(!Meta::isDebug()) {
                    @unlink($tmpFile);
                }

                if($data === null or !isset($data["totals"])) {
                    $status = new PhpstanInternalError();
                    $status->exception = "PHPStan results are corrupt - ID '{$id}', contact support with the ID for help if this persists.";
                    $result->addStatus($status);
                    Meta::getLog()->e("Failed to decode results from container '{$id}'");
                    return;
                }

                $results = $data["files"];
                $files = array_keys($results);

                foreach($files as $file) {
                    $fileData = $results[$file]["messages"];
                    foreach($fileData as $error) {
                        $status = new PhpstanLint();
                        $status->file = ltrim($file, "/source/");
                        $status->line = $error["line"];
                        $status->message = $error["message"];
                        $result->addStatus($status);
                    }
                }

            }
        } finally {
            if(!Meta::isDebug()) {
                Meta::getLog()->v("Removing PHPStan container '{$id}'");

                Lang::myShellExec("docker container rm {$id}", $stdout, $stderr, $exitCode);

                if($exitCode !== 0) {
                    $status = new PhpstanInternalError();
                    $status->exception = "Internal error occurred, ID '{$id}', contact support with the ID for help if this persists.";
                    $result->addStatus($status);
                    Meta::getLog()->e("Failed to remove container '{$id}', Status: {$exitCode}, stderr: {$stderr}");
                    return;
                }
            }
        }
    }
}
