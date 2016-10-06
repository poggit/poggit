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

namespace poggit\page\webhooks\framework;

use poggit\model\ProjectThumbnail;
use poggit\page\webhooks\PushWebhookHandler;

class DefaultBuilder extends FrameworkBuilder {
    public function getName() : string {
        return "default";
    }

    public function getVersion() : string {
        return "1.0";
    }

    public function build(PushWebhookHandler $handler, ProjectThumbnail $project, \Phar $phar) : array {
        $lintFiles = [];

        $path = $project->path;
        $pathLen = strlen($project->path);
        $stub = '<?php ';
        if($hasStub = $handler->getRepoFileByName($path . "stub.php", $contents)) {
            $phar->addFromString("stub.php", $contents);
            $lintFiles["stub.php"] = $contents;
            $stub .= 'require_once("phar://" . __FILE__ . "/stub.php"); ';
        }
        $phar->setStub($stub . '__HALT_COMPILER();');

        for($index = 0; $index < $handler->getZip()->numFiles; $index++) {
            $handler->getRepoFileByIndex($index, $fileName);
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
                substr($fileName, 0, 4) === "src/" or
                substr($fileName, 0, 10) === "resources/" or
                $hasStub and substr($fileName, 0, 6) === "stubs/"
            ) {
                $cont = null; // DO NOT REMOVE THIS LINE, REFERENCE HACK
                $handler->getRepoFileByIndex($index, $f_, $cont);
                $phar->addFromString($fileName, $cont);
                $lintFiles[$fileName] = $cont;
                printf("Included file $fileName (%d bytes)\n", strlen($cont));
            }
        }
        return $lintFiles;
    }
}
