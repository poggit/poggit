<?php

/*
 * Poggit
 *
 * Copyright (C) 2016-2017 Poggit
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

namespace poggit\module\releases\review;

use poggit\module\Module;
use poggit\release\PluginRelease;
use poggit\utils\internet\MysqlUtils;
use poggit\utils\SessionUtils;
use poggit\module\releases\review\OfficialReviewModule as Reviews;

class ReviewListModule extends Module {

    public function getName(): string {
        return "review";
    }

    public function output() {
        $reviews = MysqlUtils::query("SELECT releaseId, UNIX_TIMESTAMP(created) AS created FROM release_reviews ORDER BY created DESC LIMIT 50");
        $releases = PluginRelease::getPluginsByState(PluginRelease::RELEASE_STAGE_CHECKED, 50);
        ?>
        <html>
        <head prefix="og: http://ogp.me/ns# fb: http://ogp.me/ns/fb# object: http://ogp.me/ns/object# article: http://ogp.me/ns/article# profile: http://ogp.me/ns/profile#">
            <?php $this->headIncludes("Poggit - Reviews", "Official & User Reviews for Poggit PocketMine Plugin Releases") ?>
            <title>Poggit Plugin Reviews</title>
            <meta property="article:section" content="Reviews"/>
        </head>
        <body>
        <?php $this->bodyHeader() ?>
        <div id="body">
            <?php if(count($releases) > 0) { ?>
               <div class="review-releases">
                <?php foreach ($releases as $plugin) {
                    if (!$plugin->isPrivate) {
                        PluginRelease::pluginPanel($plugin);
                    }
                } ?>
                </div><hr />
            <?php } ?>
            <div class="review-page">
                <?php
                $relIds = array_map(function ($review) {
                    return $review["releaseId"];
                }, $reviews);
                if (count($relIds) > 0) Reviews::reviewPanel($relIds, SessionUtils::getInstance()->getLogin()["name"] ?? "", true)
                ?>
            </div>
        </div>
        <?php $this->bodyFooter() ?>
        </body>
        </html>
        <?php
    }
}
