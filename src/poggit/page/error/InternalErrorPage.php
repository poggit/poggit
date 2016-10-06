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

namespace poggit\page\error;

use poggit\page\Page;
use const poggit\RES_DIR;

class InternalErrorPage extends Page {
    public function getName() : string {
        return "err";
    }

    public function output() {
        http_response_code(500);
        ?>
        <!-- Error ref ID: <?= $this->getQuery() ?> -->
        <html>
        <head>
            <style type="text/css">
                <?php readfile(RES_DIR . "style.css") ?>
            </style>
            <title>500 Internal Server Error</title>
        </head>
        <body>
        <div id="body">
            <h1>500 Internal Server Error</h1>
            <p>A server internal error occurred. Reference ID: <code class="code"><?= $this->getQuery() ?></code></p>
        </div>
        </body>
        </html>
        <?php
    }
}
