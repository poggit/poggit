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

use poggit\account\Session;
use poggit\Meta;
use poggit\module\Module;
use function htmlspecialchars;
use function http_response_code;
use const poggit\RES_DIR;

class AccessDeniedPage extends Module {
    public $details;

    public function getName(): string {
        return "err";
    }

    public function output() {
        http_response_code(401);
        ?>
      <html>
      <head
          prefix="og: http://ogp.me/ns# fb: http://ogp.me/ns/fb# object: http://ogp.me/ns/object# article: http://ogp.me/ns/article# profile: http://ogp.me/ns/profile#">
        <title>401 Access Denied</title>
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
        <h1>401 Access Denied</h1>
        <p>Path <code class="code"><span class="verbose"><?= htmlspecialchars(Meta::root())
                    ?></span><?= htmlspecialchars($this->getQuery()) ?></code>
          cannot be accessed by your current login.</p>
          <?php
          if(isset($this->details)) {
              echo "<p>Detailed reason: ";
              echo $this->details;
              echo "</p>";
          }
          ?>
        <p>Referrer: <?= htmlspecialchars($_SERVER["HTTP_REFERER"] ?? "(none)", ENT_QUOTES, 'UTF-8') ?></p>
        <p>This incident will be reported.</p>
        <img src="https://imgs.xkcd.com/comics/incident.png"/>
      </div>
      <?php $this->flushJsList(); ?>
      </body>
      </html>
        <?php
    }
}
