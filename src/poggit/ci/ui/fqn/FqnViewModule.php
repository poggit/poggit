<?php

/*
 * poggit
 *
 * Copyright (C) 2018 SOFe
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

namespace poggit\ci\ui\fqn;

use poggit\module\HtmlModule;
use poggit\module\Module;

class FqnViewModule extends HtmlModule {
    public function output() {
        ?>
      <html>
      <head
          prefix="og: http://ogp.me/ns# fb: http://ogp.me/ns/fb# object: http://ogp.me/ns/object# article: http://ogp.me/ns/article# profile: http://ogp.me/ns/profile#">
          <?php $this->headIncludes("Namespace browser", "List of namespaces used in Poggit projects") ?>
        <title>Namespace browser</title>
          <?php Module::includeCss("ci.fqn") ?>
      </head>
      <body>
      <?php $this->bodyHeader() ?>
      <div id="body">
        <h1>Namespaces used in Poggit projects</h1>
        <p>Sort by: <select id="sort-fqn">
            <option value="SORT_NAME_NO_CASE">Name (case-insensitive)</option>
            <option value="SORT_NAME_CASE">Name (case-sensitive)</option>
            <option value="SORT_ID">Earliest occurrence</option>
            <option value="SORT_ID_REVERSE">Latest occurrence</option>
          </select></p>
        <div id="tree-container"></div>
      </div>
      <?php
      $this->bodyFooter();
      Module::queueJs("jquery.sortElements");
      Module::queueJs("ci.fqn");
      $this->flushJsList() ?>
      </body>
      </html>
        <?php
    }
}
