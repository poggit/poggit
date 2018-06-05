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

namespace poggit\debug;

use function filesize;
use poggit\Meta;
use poggit\resource\ResourceManager;
use function json_decode;
use function move_uploaded_file;

class AddResourceReceive extends DebugModule {
    public function output() {
        parent::output();
        $file = ResourceManager::getInstance()->createResource($_REQUEST["type"], $_REQUEST["mimeType"], json_decode($_REQUEST["accessFilters"]), $id, $_REQUEST["expiry"], $_REQUEST["src"] ?? "src", filesize($_FILES["file"]["size"]));
        move_uploaded_file($_FILES["file"]["tmp_name"], $file);
        ?>
      <html>
      <head
          prefix="og: http://ogp.me/ns# fb: http://ogp.me/ns/fb# object: http://ogp.me/ns/object# article: http://ogp.me/ns/article# profile: http://ogp.me/ns/profile#">
        <title>Add resource result</title>
          <?php $this->headIncludes("N/A", "Debug page") ?>
      </head>
      <body>
      <?php $this->bodyHeader() ?>
      <div id="body">
        <p>Resource ID: <?= $id ?></p>
        <p>Resource file: <?= $file ?></p>
          <?php $link = Meta::root() . "r/$id"; ?>
        <p>Resource link: <a href="<?= $link ?>"><?= $link ?></a></p>
      </div>
      <?php $this->bodyFooter() ?>
      <?php $this->flushJsList(); ?>
      </body>
      </html>
        <?php
    }
}
