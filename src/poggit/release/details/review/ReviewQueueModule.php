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

namespace poggit\release\details\review;

use poggit\account\Session;
use poggit\Meta;
use poggit\module\HtmlModule;
use poggit\module\Module;
use poggit\release\Release;
use poggit\utils\OutputManager;

class ReviewQueueModule extends HtmlModule {
    public function output() {
        if(Meta::getAdmlv() < Meta::ADMLV_REVIEWER) {
            Meta::redirect("plugins");
            return;
        }
        $releases = Release::getReviewQueue(Release::STATE_SUBMITTED, 1000, Release::STATE_SUBMITTED);
        $minifier = OutputManager::startMinifyHtml();
        ?>
      <html>
      <head
          prefix="og: http://ogp.me/ns# fb: http://ogp.me/ns/fb# object: http://ogp.me/ns/object# article: http://ogp.me/ns/article# profile: http://ogp.me/ns/profile#">
          <?php $this->headIncludes("Poggit - Review", "Review Poggit PocketMine Plugin Releases") ?>
        <title>Poggit Plugin Review</title>
        <meta property="article:section" content="Review"/>
      </head>
      <body>
      <?php $this->bodyHeader() ?>
      <div id="body">
        <div id="review-releases">
            <?php foreach($releases as $plugin) {
                Release::pluginPanel($plugin);
            } ?>
        </div>
      </div>
      <?php
      $this->bodyFooter();
      Module::queueJs("review.queue");
      $this->flushJsList(); ?>
      </body>
      </html>
        <?php
        OutputManager::endMinifyHtml($minifier);
    }
}
