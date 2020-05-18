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

use poggit\Mbd;
use poggit\Meta;
use poggit\module\Module;
use poggit\account\Session;
use function htmlspecialchars;
use function http_response_code;
use const poggit\RES_DIR;

class NotFoundPage extends Module {
    public function getName(): string {
        return "err";
    }

    public function output() {
        http_response_code(404);
        ?>
      <html>
      <head
          prefix="og: http://ogp.me/ns# fb: http://ogp.me/ns/fb# object: http://ogp.me/ns/object# article: http://ogp.me/ns/article# profile: http://ogp.me/ns/profile#">
        <title>404 Not Found</title>
        <script>
            <?php Mbd::analytics() ?>
            <?php Mbd::gaCreate() ?>
            ga('send', 'event', 'Special', 'NotFound', window.location.pathname, {nonInteraction: true});
        </script>
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
      </head>
      <body>
      <div id="body">
        <h1>404 Not Found</h1>
        <p>Path <code class="code"><span class="verbose"><?= htmlspecialchars(Meta::root()) ?></span>
                <?= htmlspecialchars($this->getQuery()) ?>
          </code>,
          does not exist or is not visible to you.</p>
      </div>
      <?php $this->flushJsList(); ?>
      </body>
      </html>
        <?php
    }
}
