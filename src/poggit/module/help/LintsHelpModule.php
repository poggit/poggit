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

namespace poggit\module\help;

use poggit\module\Module;

class LintsHelpModule extends Module {
    public function getName(): string {
        return "help.lint";
    }

    public function output() {
        ?>
        <html>
        <head prefix="og: http://ogp.me/ns# fb: http://ogp.me/ns/fb# object: http://ogp.me/ns/object# article: http://ogp.me/ns/article# profile: http://ogp.me/ns/profile#">
            <title>Lint | Help | Poggit</title>
            <?php $this->headIncludes("Poggit Help: Lint", "Help information about lint provided by Poggit CI") ?>
        </head>
        <body>
        <?php $this->bodyHeader() ?>
        <div id="body">
            <h1>Lint</h1>
            <!-- TODO -->
        </div>
        <?php $this->bodyFooter() ?>
        </body>
        </html>
        <?php
    }
}
