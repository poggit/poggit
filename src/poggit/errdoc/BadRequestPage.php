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

namespace poggit\errdoc;

use poggit\module\Module;
use poggit\account\Session;
use function htmlspecialchars;
use function http_response_code;
use function readfile;
use const poggit\RES_DIR;

class BadRequestPage extends Module {
    /** @var bool */
    private $escape = true;

    public function __construct(string $query, bool $escape = true) {
        parent::__construct($query);
        $this->escape = $escape;
    }

    public function getName(): string {
        return "err";
    }

    public function output() {
        http_response_code(400);
        ?>
      <html>
      <head
          prefix="og: http://ogp.me/ns# fb: http://ogp.me/ns/fb# object: http://ogp.me/ns/object# article: http://ogp.me/ns/article# profile: http://ogp.me/ns/profile#">
        <style type="text/css"><?php
            try {
                $session = Session::getInstance();
                if($session === null || !$session->isLoggedIn()) {
                    readfile(RES_DIR . "style.css");
                }
                if($session->getLogin()["opts"]->darkMode ?? false) {
                    readfile(RES_DIR . "style-dark.css");
                } else {
                    readfile(RES_DIR . "style.css");
                }
            } catch (\Exception $e){
                readfile(RES_DIR . "style.css");
            }
            ?></style>
        <title>400 Bad Request</title>
      </head>
      <body>
      <div id="body">
        <h1>400 Bad Request</h1>
        <p><?= $this->getQuery() ? ($this->escape ? htmlspecialchars($this->getQuery()) : $this->getQuery()) : "You entered an invalid link that points to an invalid resource." ?></p>
      </div>
      </body>
      </html>
        <?php
    }
}
