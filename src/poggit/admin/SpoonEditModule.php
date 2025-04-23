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

namespace poggit\admin;

use poggit\Mbd;
use poggit\Meta;
use poggit\module\HtmlModule;
use poggit\module\Module;
use poggit\utils\PocketMineApi;
use function array_reverse;
use function htmlspecialchars;

class SpoonEditModule extends HtmlModule {
    public function output() {
        if(Meta::getAdmlv() < Meta::ADMLV_ADMIN) {
            $this->errorAccessDenied("Use https://poggit.pmmp.io/pmapis to see the API list");
        }
        ?>
      <html>
      <head
          prefix="og: http://ogp.me/ns# fb: http://ogp.me/ns/fb# object: http://ogp.me/ns/object# article: http://ogp.me/ns/article# profile: http://ogp.me/ns/profile#">
        <title>Edit spoons</title>
        <style>
          .editable {
            cursor: pointer;
          }
        </style>
          <?php $this->headIncludes("Edit spoons") ?>
      </head>
      <body>
      <?php $this->bodyHeader() ?>
      <div id="body">
        <h1>Spoon editor</h1>
        <h2>Version list</h2>
        <button id="add-version" class="btn btn-primary btn-lg btn-block">Add version</button>
          <?php foreach(array_reverse(PocketMineApi::$VERSIONS, true) as $version => $data) { ?>
            <h3><?= Mbd::esq($version) ?> (#<?= $data["id"] ?>)</h3>
            <div class="spoon-holder" data-spoon-id="<?= $data["id"] ?>">
              <p>
                Incompatible: <input type="checkbox" class="editable" data-field="incompatible"
                      <?= $data["incompatible"] ? "checked" : "" ?>/>
              </p>
            </div>
          <?php } ?>
      </div>
      <div id="add-version-dialog" style="display: none;" title="Add version">
        <label for="dialog-name">Name</label> <input type="text" id="dialog-name"/><br/>
        <label for="dialog-incompatible">Incompatible</label> <input type="checkbox" id="dialog-incompatible"/><br/>
      </div>
      <?php $this->bodyFooter() ?>
      <?php Module::queueJs("admin.spoon.edit") ?>
      <?php $this->flushJsList() ?>
      </body>
      </html>
        <?php
    }
}
