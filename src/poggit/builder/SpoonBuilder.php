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

class SpoonBuilder extends ProjectBuilder {

    public function getName(): string {
        return "spoon";
    }

    public function getVersion(): string {
        return "1.0";
    }

    protected function build(Phar $phar, RepoZipball $zipball, WebhookProjectModel $project): BuildResult {
        $this->project = $project;
        $this->tempFile = Poggit::getTmpFile(".php");
        $result = new BuildResult();
        $path = $project->path;
        $stubFile = "src/pocketmine/PocketMine.php";;
        $phar->setStub('<?php define("pocketmine\\\\PATH", "phar://". __FILE__ ."/"); require_once("phar://". __FILE__ ."/src/pocketmine/PocketMine.php");  __HALT_COMPILER();');
        $manifest = $zipball->getContents($path . "plugin.yml");

        foreach($zipball->iterator("", true) as $file => $reader) {
            if(!LangUtils::startsWith($file, $project->path)) continue;
            if(substr($file, -1) === "/") continue;
            if(LangUtils::startsWith($file, $project->path . "resources/") or LangUtils::startsWith($file, $project->path . "src/")) {
                $phar->addFromString($file, $contents = $reader());
                if(substr($file, -4) === ".php" and substr($file, 0, 14) !== "src/spl/stubs/") $this->lintPhpFile($result, $file, $contents, false, false);
            }
        }

        return $result;
    }
}
