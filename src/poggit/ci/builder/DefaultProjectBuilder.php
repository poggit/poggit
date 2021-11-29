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

    protected function build(Phar $phar, RepoZipball $zipball, WebhookProjectModel $project, int $buildId, bool $isRepoPrivate): BuildResult {
        $this->project = $project;
        $this->tempFile = Meta::getTmpFile(".php");
        $result = new BuildResult();
        $path = $project->path;
        $phar->addFromString(".poggit", "");
        if(isset($project->manifest["stub"])) {
            $stubFile = $project->manifest["stub"];
            if($stubFile[0] === "/") { // absolute
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
                $dirsToAdd[trim($repoPath[0] === "/" ? substr($repoPath, 1) : $project->path . $repoPath, "/") . "/"]
                    = trim($pharPath === "=" ? $repoPath : $pharPath, "/") . "/";
            }
        }
        if(isset($project->manifest["includeFiles"])) {
            foreach((array) $project->manifest["includeFiles"] as $repoPath => $pharPath) {
                $filesToAdd[$repoPath[0] === "/" ? substr($repoPath, 1) : ($project->path . $repoPath)] = $pharPath;
            }
        }
        $filesToExclude = [];
        $dirsToExclude = [];
        if(isset($project->manifest["excludeFiles"])) {
            foreach((array) $project->manifest["excludeFiles"] as $file) {
                $filesToExclude[] = $file[0] === "/" ? substr($file, 1) : ($project->path . $file);
            }
        }
        if(isset($project->manifest["excludeDirs"])) {
            foreach((array) $project->manifest["excludeDirs"] as $dir) {
                $dirsToExclude[] = trim($dir[0] === "/" ? substr($dir, 1) : ($project->path . $dir), "/") . "/";
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

        if($result->worstLevel <= BuildResult::LEVEL_LINT){
            $api = yaml_parse($pluginYml)["api"];
            if($api !== null){
                if(!is_array($api)) $api = [$api];

                // Test API:
                $apiValid = true;
                foreach($api as $v){
                    /*if(version_compare($v, "3.0.0", "<")) {
                        $apiValid = false;
                        break;
                    }*/
                    if(version_compare($v, "4.0.0", ">=")){
                        $apiValid = false;
                        break;
                    }
                }

                if($apiValid){
                    if(!$isRepoPrivate){
                        $phar->stopBuffering();
                        $this->runDynamicCommandList($phar->getPath(), yaml_parse($pluginYml)["name"] ?? null, yaml_parse($pluginYml)["depend"] ?? [], $buildId);
                        $phar->startBuffering();
                    }
                }
            }
        }

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

    /**
     * @param string          $pharPath
     * @param string          $pluginName
     * @param string|string[] $pluginDep
     * @param int             $buildId
     */
    private function runDynamicCommandList(string $pharPath, string $pluginName, $pluginDepNames, int $buildId){
        if($pluginName === null){
            Meta::getLog()->e("Failed to start dyncmdlist, no plugin name found.");
            return;
        }
        $pluginName = escapeshellarg($pluginName);
        $pharPath = escapeshellarg($pharPath);
        if(!is_array($pluginDepNames)) $pluginDepNames = [$pluginDepNames];

        $id = escapeshellarg("dyncmdlist-" . substr(Meta::getRequestId() ?? bin2hex(random_bytes(8)), 0, 4) . "-" . bin2hex(random_bytes(4)));

        Meta::getLog()->v("Starting dyncmdlist flow with ID '{$id}'");

        try{
            $wrapCmd = escapeshellarg("./wrapper.sh $pluginName");
            $dockerCmds = [
                "docker create --name {$id} --cpus=1 --memory=256M pmmp/dyncmdlist:0.1.4 bash -c $wrapCmd",
                "docker cp {$pharPath} {$id}:/input/{$pluginName}.phar"
            ];

            // Get all plugin dependencies declared.
            $pluginDep = [strtolower($pluginName)];
            while(sizeof($pluginDepNames) > 0){
                $pluginDepName = strtolower(array_shift($pluginDepNames));
                if(!in_array($pluginDepName, $pluginDep)) {
                    $pluginDepPath = $this->getPluginPath($pluginDepName);
                    if($pluginDepPath !== null) {
                        $dockerCmds[] = "docker cp {$pluginDepPath} {$id}:/input/".escapeshellarg($pluginDepName).".phar";
                        $pluginDep[] = $pluginDepName;
                        $pluginDepDep = $this->getPluginDependencies($pluginDepPath);
                        if($pluginDepDep !== null){
                            $pluginDepNames = array_merge($pluginDepNames, $pluginDepDep);
                        }
                    } else {
                        // Failed to get a required dependency so the plugin won't load, no point running the container.
                        Meta::getLog()->w("Failed to get dependency '{$pluginDepName}', aborting dyncmdlist.");
                        return;
                    }
                }
            }
            $dockerCmds[] = "docker start -a {$id}";

            foreach($dockerCmds as $dockerCmd){
                Meta::getLog()->v("Running command: $dockerCmd");
                $stdout = $stderr = $exitCode = null;
                Lang::myShellExec($dockerCmd, $stdout, $stderr, $exitCode);
                if($exitCode !== 0){
                    Meta::getLog()->e("dyncmdlist failed with code '{$exitCode}', command: {$dockerCmd}, stdout: {$stdout}, stderr: {$stderr}");
                    return;
                }
            }
        } finally {
            if(!Meta::isDebug()) {
                Lang::myShellExec("docker container rm {$id}", $stdout2, $stderr2, $exitCode2);
            }
        }

        $result = json_decode($stdout, true);

        if($result === null || !isset($result["status"])){
            Meta::getLog()->e("Failed to parse results from dyncmdlist, stdout: {$stdout}, stderr: {$stderr}, json_msg: ".json_last_error_msg());
            return;
        }

        if($result["status"] === false){
            // OOTB Plugins should not fail.
            Meta::getLog()->w("dyncmdlist failed with error '{$result["error"]}'");
            return;
        }

        if(sizeof($result["commands"]) === 0) Meta::getLog()->v("dyncmdlist found no commands.");

        // TODO orphans.

        foreach($result["commands"] as $cmd){
            Mysql::query("INSERT INTO known_commands (name, description, `usage`, class, buildId) VALUES (?,?,?,?,?);",
                "ssssi",
                $cmd["name"],
                $cmd["description"],
                substr($cmd["usage"], 0, 255),
                $cmd["class"],
                $buildId
            );
            $count = sizeof($cmd["aliases"]);
            $params = [];
            foreach($cmd["aliases"] as $alias){
                $params[] = $cmd["name"];
                $params[] = $buildId;
                $params[] = $alias;
            }
            if($count !== 0) {
                Mysql::query("INSERT INTO known_aliases (name, buildId, alias) VALUES ".substr(str_repeat("(?,?,?),", $count), 0, -1),
                    str_repeat("sis", $count),
                    ...$params
                );
            }
        }
    }

    private function runPhpstan(RepoZipball $zipball, BuildResult $result, WebhookProjectModel $project){
        $id = "phpstan-" . substr(Meta::getRequestId() ?? bin2hex(random_bytes(8)), 0, 4) . "-" . bin2hex(random_bytes(4));

        Meta::getLog()->v("Starting PHPStan flow with ID '{$id}'");

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

        $pluginYaml = yaml_parse($zipball->getContents($project->path."plugin.yml"));
        if(!is_array($pluginYaml["depend"] ?? [])) $pluginYaml["depend"] = [$pluginYaml["depend"]]; //One declared plugin non array style.
        if(!is_array($pluginYaml["softdepend"] ?? [])) $pluginYaml["softdepend"] = [$pluginYaml["softdepend"]]; //One declared plugin non array style.
        $pluginDepNames = array_merge(($pluginYaml["depend"] ?? []), ($pluginYaml["softdepend"] ?? []));

        foreach($pluginDepNames as $name) {
            $path = $this->getPluginPath($name);
            if($path !== null){
                $pluginDep[$name] = $path;
            }
        }

        try {
            $pluginPath = "/".trim($project->path,"/");
            $pluginInternalPath = $zipball->getZipPath();

            if($pluginPath !== "/") $pluginPath .= "/";
            /*
             * PluginPath should be '/' when in no directory or '/Dir1/Dir2/etc/' when in directory's
             * (notice beginning '/' and end '/')
             */

            Lang::myShellExec("docker create --cpus=1 --memory=256M -e PLUGIN_PATH={$pluginPath} --name {$id} pmmp/poggit-phpstan:0.2.4", $stdout, $stderr, $exitCode);

            if($exitCode !== 0) {
                $status = new PhpstanInternalError();
                $status->exception = "PHPStan failed with ID '{$id}', contact support with the ID for help if this persists.";
                $result->addStatus($status);
                Meta::getLog()->e("Failed to create docker container with id '{$id}', Status: {$exitCode}, stderr: {$stderr}");
                return;
            }

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

            Lang::myShellExec("docker start -a {$id}", $stdout, $stderr, $exitCode);

            if($exitCode !== 0 and ($exitCode < 3 or $exitCode > 8)) {
                $status = new PhpstanInternalError();
                $status->exception = "PHPStan failed with ID '{$id}', contact support with the ID for help if this persists.";
                $result->addStatus($status);
                Meta::getLog()->e("PHPStan failed, Unhandled exit code from container '{$id}', Status: {$exitCode}, stderr: {$stderr}");
                return;
            }

            switch($exitCode) {
                case 6:
                case 0:
                    break;

                case 3:
                    Meta::getLog()->e("Failed to extract plugin, Status: 3, stderr: {$stderr}");
                    $status = new PhpstanInternalError();
                    $status->exception = "PHPStan failed with ID '{$id}', Failed to extract the plugin. Contact support with the ID for help if this persists.";
                    $result->addStatus($status);
                    return;

                case 4:
                    Meta::getLog()->e("Failed to extract dependency's, Status: 4, stderr: {$stderr}");
                    $status = new PhpstanInternalError();
                    $status->exception = "PHPStan failed with ID '{$id}', Failed to extract dependency's. Contact support with the ID for help if this persists.";
                    $result->addStatus($status);
                    return;

                case 5:
                    Meta::getLog()->e("Composer failed to install dependencies, Status: 5, stderr: {$stderr}");
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
                        Meta::getLog()->e("Unknown problem (exit code: 7 (original 255)), stderr: {$stderr}");
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
                    Meta::getLog()->e("Failed to decode results from container '{$id}', Container errors(if any): {$stderr}");
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

    /**
     * @param string $plugin
     * @return string|null
     */
    private function getPluginPath(string $plugin){
        $check = Mysql::query("SELECT buildId FROM releases WHERE name = ? AND state >= ? ORDER BY buildId DESC LIMIT 1", "si", $plugin, Config::MIN_PUBLIC_RELEASE_STATE);
        if(count($check) > 0) {
            $buildId = (int) $check[0]["buildId"];
            $rows = Mysql::query("SELECT resourceId FROM builds WHERE buildId = ? AND class = ?", "ii", $buildId, ProjectBuilder::BUILD_CLASS_DEV);
            if(count($rows) > 0) {
                $resourceId = (int) $rows[0]["resourceId"];
                return ResourceManager::pathTo($resourceId, "phar");
            }
        }
        return null;
    }

    /**
     * @param string $pluginPath
     * @return string[]|null
     */
    private function getPluginDependencies(string $pluginPath){
        if(substr($pluginPath, -5) !== ".phar"){
            Meta::getLog()->e("Unknown plugin dependency path received, path: '{$pluginPath}'");
            return null;
        }
        $plugin = "phar://{$pluginPath}/plugin.yml";
        $pluginManifest = file_get_contents($plugin);
        if($pluginManifest !== false){
            $pluginManifest = yaml_parse($pluginManifest);
            if($pluginManifest !== false){
                $dep = $pluginManifest["depend"] ?? [];
                if(!is_array($dep)) return [(string)$dep];
                return $dep;
            }
        }
        return null;
    }
}
