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

namespace poggit\release\submit;

use poggit\Meta;
use poggit\module\HtmlModule;
use poggit\module\Module;
use poggit\utils\OutputManager;

class SubmitModule extends HtmlModule {
    public function output() {
        $minifier = OutputManager::startMinifyHtml();
        ?>
      <html>
      <head
          prefix="og: http://ogp.me/ns# fb: http://ogp.me/ns/fb# object: http://ogp.me/ns/object# article: http://ogp.me/ns/article# profile: http://ogp.me/ns/profile#">
          <?php $this->headIncludes("Submit Plugin") ?>
        <title>Loading submit form...</title>
      </head>
      <body>
      <?php $this->bodyHeader(); ?>
      <div id="body" class="main-wrapper real-submit-wrapper">
        <div class="submit-title" id="submit-title">
          <div id="submit-title-action"></div>
          <div class='submit-title-gh' id="submit-title-gh"></div>
          <div class='submit-title-badge' id="submit-title-badge">
          </div>
        </div>
        <div class="submit-intro">
          <div id="submit-intro-last-name" style="display: none;"></div>
          <p class="remark">Your plugin will be reviewed by Poggit reviewers according to <a
                href="<?= Meta::root() ?>rules.edit" target="_blank">plugin submission rules</a>.</p>
          <p class="remark">
            <strong>Do not submit plugins written by other people without prior consent from the author. This may
              be considered as plagiarism, and your access to Poggit may be blocked if you do so.</strong>
            If you want them to be available on Poggit, please request it at the
            <a href="https://github.com/poggit-orphanage/office/issues" target="_blank">Poggit Orphanage
              Office</a>.
            <br/>
            If you only rewrote the plugin but did not take any code from the original author, consider using a
            new plugin name, or at least add something like <code>_New</code> behind the plugin name. Consider
            adding the original author as a <em>Requester</em> in the <em>Producers</em> field below.<br/>
            If you have used some code from the original author but have made major changes to the plugin, you are
            allowed to submit this plugin from your <em>fork</em> repo, but you <strong>must</strong> add the
            original author as a <em>collaborator</em> in the <em>Producers</em> field below.
          </p>
          <p class="remark">Note: If you don't submit this form within three hours after loading this page, this
            form will become invalid and you will have to reload this page.</p>
        </div>
        <div class="form-table">
          <h3>Loading...</h3>
          <p>If this page doesn't load in a few seconds, try refreshing the page. JavaScript must be enabled to
            use this page.</p>
        </div>
      </div>
      <div id="wait-spinner" class="loading">Loading...</div>
      <?php
      $this->bodyFooter();
      Module::queueJs("newSubmit");
      $this->flushJsList();
      ?>
      </body>
      </html>
        <?php
        OutputManager::endMinifyHtml($minifier);
    }
}
