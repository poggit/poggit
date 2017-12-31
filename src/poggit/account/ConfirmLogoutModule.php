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

declare(strict_types=1);

namespace poggit\account;

use poggit\module\HtmlModule;

class ConfirmLogoutModule extends HtmlModule {
    public function output() {
        ?>
      <html>
      <head
          prefix="og: http://ogp.me/ns# fb: http://ogp.me/ns/fb# object: http://ogp.me/ns/object# article: http://ogp.me/ns/article# profile: http://ogp.me/ns/profile#">
          <?php $this->headIncludes("Logout from Poggit", "Poggit will forget your GitHub login") ?>
      </head>
      <body>
      <?php $this->bodyHeader() ?>
      <div id="body">
        <h1>Logout</h1>
        <p>Do you really want to logout?</p>
        <span class="action" onclick="logout()">Logout</span>
      </div>
      <?php $this->bodyFooter() ?>
      <?php $this->flushJsList(); ?>
      </body>
      </html>
        <?php
    }
}
