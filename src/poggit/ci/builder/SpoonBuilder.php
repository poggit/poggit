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
use poggit\ci\RepoZipball;
use poggit\Meta;
use poggit\utils\lang\LangUtils;
use poggit\webhook\WebhookProjectModel;

class SpoonBuilder extends ProjectBuilder {
    private $versionName;

    public function getName(): string {
        return "spoon";
    }

    public function getVersion(): string {
        return "1.0";
    }

    protected function build(Phar $phar, RepoZipball $zipball, WebhookProjectModel $project): BuildResult {
        $this->project = $project;
        $this->tempFile = Meta::getTmpFile(".php");
        $result = new BuildResult();
        $phar->setStub('<?php require_once("phar://". __FILE__ ."/src/pocketmine/PocketMine.php");  __HALT_COMPILER();');

        foreach($zipball->iterator("", true) as $file => $reader) {
            if(!LangUtils::startsWith($file, $project->path)) continue;
            if(substr($file, -1) === "/") continue;
            if(LangUtils::startsWith($file, $project->path . "src/")) {
                if($file === $project->path . "src/pocketmine/PocketMine.php") {
                    $contents = $reader();
                    $contents = preg_replace_callback(/** @lang RegExp */
                        '%^([ \t]+const VERSION = ")(.*)";$%m', function ($match) use ($phar) {
                        Meta::getLog()->jd($match);
                        $this->versionName = $match[2] . "+poggit." . $phar->getMetadata()["projectBuildNumber"];
                        return $match[1] . $this->versionName . '";';
                    }, $contents);
                    echo "[*] Injected PocketMine version: $this->versionName\n";
                    $phar->addFromString($file, $contents);
                } else {
                    $phar->addFromString($file, $contents = $reader());
                }
                if(substr($file, -4) === ".php" and substr($file, 0, 14) !== "src/spl/stubs/") $this->lintPhpFile($result, $file, $contents, false, false);
            }
            // TODO composer support
        }

        return $result;
    }
}
