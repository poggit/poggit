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

namespace poggit\module\webhooks\framework;

use poggit\model\ProjectThumbnail;
use poggit\module\webhooks\RepoZipball;
use poggit\Poggit;

class DefaultBuilder extends FrameworkBuilder {
    public function getName() : string {
        return "default";
    }

    public function getVersion() : string {
        return "1.0";
    }

    public function build(RepoZipball $zipball, ProjectThumbnail $project, \Phar $phar) : array {
        $lintFiles = [];

        $path = $project->path;
        $pathLen = strlen($path);
        $stub = '<?php ';
        if($hasStub = $zipball->isFile($path . "stub.php")) {
            $phar->addFromString("stub.php", $contents = $zipball->getContents($path . "stub.php"));
            $lintFiles["stub.php"] = $contents;
            $stub .= 'require_once("phar://" . __FILE__ . "/stub.php"); ';
        }
        $phar->setStub($stub . '__HALT_COMPILER();');

        /**
         * @var string   $fileName
         * @var \Closure $cont
         */
        foreach($zipball->callbackIterator() as $fileName => $cont) {
            if(strlen($fileName) < $pathLen) {
                continue;
            }
            if(substr($fileName, 0, $pathLen) !== $path) {
                continue;
            }
            $fileName = substr($fileName, $pathLen);
            if(substr($fileName, -1) === "/") {
                continue;
            }
            if($fileName === "plugin.yml" or
                Poggit::startsWith($fileName, "src/") or
                Poggit::startsWith($fileName, "resources/") or
                $hasStub and Poggit::startsWith($fileName, "stubs/")
            ) {
                $contents = $cont();
                $phar->addFromString($fileName, $contents);
                $lintFiles[$fileName] = $contents;
                printf("Included file $fileName (%d bytes)\n", strlen($contents));
            }
        }
        return $lintFiles;
    }
}
