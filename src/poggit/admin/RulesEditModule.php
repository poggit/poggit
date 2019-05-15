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

use poggit\Meta;
use poggit\module\HtmlModule;
use poggit\module\Module;
use poggit\utils\internet\Mysql;
use function htmlspecialchars;

class RulesEditModule extends HtmlModule {
    public function output() {
        $admin = Meta::getAdmlv() >= Meta::ADMLV_ADMIN;
        $rules = Mysql::query("SELECT id, title, content, uses FROM submit_rules ORDER BY id");
        ?>
      <html>
      <head
          prefix="og: http://ogp.me/ns# fb: http://ogp.me/ns/fb# object: http://ogp.me/ns/object# article: http://ogp.me/ns/article# profile: http://ogp.me/ns/profile#">
        <title><?= $admin ? "Edit" : "View" ?> rules</title>
          <?php if($admin) { ?>
        <style>
          .editable {
            cursor: pointer;
          }
        </style>
              <?php
          } ?>
          <?php $this->headIncludes($admin ? "Edit rules" : "View rules") ?>
      </head>
      <body>
      <?php $this->bodyHeader() ?>
      <div id="body">
        <h1>Rules <?= $admin ? "Editor" : "Viewer" ?></h1>
        <h2>Rule list</h2>
          <?php if($admin) { ?>
            <button id="add-rule" class="btn btn-primary btn-lg btn-block">Add rule</button>
          <?php } ?>
        <ul>
            <?php foreach($rules as $rule) { ?>
              <li class="rule-holder" data-rule-id="<?= $rule["id"] ?>">
                <strong><?= $rule["id"] ?></strong>:
                <strong class="editable" data-field="title"><?= htmlspecialchars($rule["title"]) ?></strong>
                (<?= $rule["uses"] ?>)<br/>
                <span class="editable" data-field="content"><?= htmlspecialchars($rule["content"]) ?></span>
              </li>
            <?php } ?>
        </ul>
        <div id="add-rule-dialog" style="display: none;" title="Add rule">
          <label for="dialog-id">ID</label> <input type="text" id="dialog-id"/><br/>
          <label for="dialog-title">Title</label> <input type="text" id="dialog-title"/><br/>
          <label for="dialog-content">Content</label>
          <textarea id="dialog-content" cols="200" rows="10"></textarea>
        </div>
          <?php $this->bodyFooter() ?>
          <?php if($admin) {
              Module::queueJs("admin.rule.edit");
          } ?>
          <?php $this->flushJsList() ?>
      </body>
      </html>
        <?php
    }
}
