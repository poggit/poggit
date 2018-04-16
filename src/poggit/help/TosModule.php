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

namespace poggit\help;

use poggit\module\HtmlModule;
use poggit\utils\OutputManager;
use const poggit\ASSETS_PATH;

class TosModule extends HtmlModule {
    public function output() {
        $minifier = OutputManager::startMinifyHtml();
        ?>
      <html>
      <head
          prefix="og: http://ogp.me/ns# fb: http://ogp.me/ns/fb# object: http://ogp.me/ns/object# article: http://ogp.me/ns/article# profile: http://ogp.me/ns/profile#">
          <?php $this->headIncludes("Poggit - Terms of Service", "Poggit ToS") ?>
        <title>Terms of Service | Poggit</title>
      </head>
      <body>
      <?php $this->bodyHeader() ?>
      <div id="body">
          <?php include ASSETS_PATH . "incl/tos.php"; ?>
      </div>
      <?php $this->bodyFooter() ?>
      <?php $this->flushJsList(); ?>
      </body>
      </html>
        <?php
        OutputManager::endMinifyHtml($minifier);
    }
}
