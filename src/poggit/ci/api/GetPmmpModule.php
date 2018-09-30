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

namespace poggit\ci\api;

use poggit\Meta;
use poggit\module\Module;
use function array_slice;
use function explode;
use function header;
use function implode;
use function strpos;

class GetPmmpModule extends Module {
    public function output() {
        $arg = $this->getQuery();

        if(strpos($arg, "/") !== false) {
            $args = explode("/", $arg);
            $arg = implode("/", array_slice($args, 0, -1));
        }

        if($arg === "html") Meta::redirect("https://jenkins.pmmp.io", true);

        header("Content-Type: text/plain");
        echo <<<EOM
PMMP builds on Poggit are temporarily disabled. Please use the official Jenkins server at <https://jenkins.pmmp.io> for development builds of PMMP.

There is no ETA for bringing back PMMP builds on Poggit.
EOM;
    }
}
