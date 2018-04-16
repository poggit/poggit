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

namespace poggit\module;

use poggit\utils\OutputManager;
use TypeError;
use function get_class;
use function htmlspecialchars;
use function implode;
use function is_array;

abstract class VarPageModule extends HtmlModule {
    /** @var VarPage */
    public $varPage;

    public function output() {
        try {
            $this->selectPage();
            throw new TypeError("No page returned");
        } catch(VarPage $page) {
            $this->varPage = $page;
        }
        $minifier = OutputManager::startMinifyHtml();
        ?>
      <!DOCTYPE html>
      <html lang="en">
      <head
          prefix="og: http://ogp.me/ns# fb: http://ogp.me/ns/fb# object: http://ogp.me/ns/object# article: http://ogp.me/ns/article# profile: http://ogp.me/ns/profile#">
          <?php
          $ogResult = $this->varPage->og();
          if(is_array($ogResult)) {
              list($type, $link) = $ogResult;
          } else {
              $type = $ogResult;
              $link = "";
          }
          $title = htmlspecialchars($this->varPage->getTitle() . $this->titleSuffix());
          $this->headIncludes($title, $this->varPage->getMetaDescription(), $type, $link);
          echo '<title>';
          echo $title;
          echo '</title>';
          $this->includeMoreJs();
          $this->varPage->includeMoreJs($this);
          ?>
      </head>
      <body>
      <?php $this->bodyHeader() ?>
      <div id="body">
          <?php $this->moduleHeader(); ?>
        <!-- VarPage: <?= get_class($this->varPage) ?> -->
        <div class="main-wrapper <?= implode(" ", $this->varPage->bodyClasses()) ?>">
            <?php $this->varPage->output(); ?>
        </div>
          <?php $this->moduleFooter(); ?>
      </div>
      <?php $this->bodyFooter() ?>
      <?php $this->flushJsList(); ?>
      </body>
      </html>
        <?php
        OutputManager::endMinifyHtml($minifier);
    }

    public function moduleHeader() {
    }

    public function moduleFooter() {
    }

    /**
     * @throws VarPage
     */
    protected abstract function selectPage();

    protected function titleSuffix(): string {
        return " | Poggit";
    }

    protected function includeMoreJs() {
    }
}
