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

namespace poggit\builder;

use Phar;
use poggit\builder\lint\BuildResult;
use poggit\module\webhooks\repo\WebhookProjectModel;
use const poggit\ASSETS_PATH;

class PoggitVirionBuilder extends ProjectBuilder {

    public function getName(): string {
        return "poggit-lib";
    }

    public function getVersion(): string {
        return "1.0";
    }

    protected function build(Phar $phar, RepoZipball $zipball, WebhookProjectModel $project): BuildResult {
        $result = new BuildResult();
        $phar->startBuffering();
        $phar->setStub(file_get_contents(ASSETS_PATH));

        // TODO build
        // TODO lint, especially mutated viral genomes
        return $result;
    }
}
