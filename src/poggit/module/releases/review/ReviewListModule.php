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

use poggit\Poggit;
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
        $reviews = MysqlUtils::query("SELECT rev.releaseId, rel.state AS state, UNIX_TIMESTAMP(rev.created) AS created FROM release_reviews rev INNER JOIN releases rel ON rel.releaseId = rev.releaseId ORDER BY created DESC LIMIT 50");
        $releases = PluginRelease::getPluginsByState(PluginRelease::RELEASE_STAGE_CHECKED, 100);
        $session = SessionUtils::getInstance();
        $user = $session->getLogin()["name"] ?? "";
        $adminlevel = Poggit::getAdmlv($user);
        ?>
        <html>
        <head prefix="og: http://ogp.me/ns# fb: http://ogp.me/ns/fb# object: http://ogp.me/ns/object# article: http://ogp.me/ns/article# profile: http://ogp.me/ns/profile#">
            <?php $this->headIncludes("Poggit - Review", "Review Poggit PocketMine Plugin Releases") ?>
            <title>Poggit Plugin Review</title>
            <meta property="article:section" content="Review"/>
        </head>
        <body>
        <?php $this->bodyHeader() ?>
        <div id="body">
        <?php if(!$session->isLoggedIn()) { ?>
            <div><h2>Please login to leave reviews</h2></div>
            <?php } ?>
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
                $relIds = array_map(function ($review) use ($session, $adminlevel) {
                    return ($adminlevel >= Poggit::ADM || ($session->isLoggedIn() && $review["state"] >= PluginRelease::RELEASE_STAGE_CHECKED) || (!$session->isLoggedIn() && $review["state"] > PluginRelease::RELEASE_STAGE_CHECKED)) ? $review["releaseId"] : null;
                }, $reviews);
                if (count($relIds) > 0) Reviews::reviewPanel($relIds, $user, true)
                ?>
            </div>
        </div>
        <?php $this->bodyFooter() ?>
        </body>
        </html>
        <?php
    }
}
