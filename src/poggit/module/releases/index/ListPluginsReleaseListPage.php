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

namespace poggit\module\releases\index;

use poggit\module\VarPage;
use poggit\Poggit;
use poggit\release\PluginRelease;
use poggit\utils\SessionUtils;
use poggit\module\releases\review\OfficialReviewModule as Reviews;

abstract class ListPluginsReleaseListPage extends VarPage
{
    /**
     * @param IndexPluginThumbnail[] $plugins
     */
    protected function listPlugins(array $plugins)
    {
        $session = SessionUtils::getInstance();
        $adminlevel = $session->isLoggedIn() ? Poggit::getAdmlv($session->getLogin()["name"]) : 0; ?>
        <div class="plugins-wrapper">
            <div class="plugin-index">
                <?php if (SessionUtils::getInstance()->isLoggedIn()) { ?>
                    <div class="listplugins-sidebar">
                        <div class="myreleaseswrapper toggle" data-name="My Releases">
                            <?php foreach ($plugins as $plugin) {
                                if ($plugin->isMine) {
                                    PluginRelease::pluginPanel($plugin);
                                }
                            } ?>
                        </div>
                    </div>
                <?php } ?>
                <div class="mainreleaselist">
                    <div id="searchresults" class="searchresults"></div>
                    <?php foreach ($plugins as $plugin) {
                        if (!$plugin->isMine && !$plugin->isPrivate) {
                            PluginRelease::pluginPanel($plugin);
                        }
                    } ?>
                </div>
            </div>
            <?php if (Reviews::SHOW_REVIEWS_IN_RELEASE || $adminlevel >= Poggit::MODERATOR) { ?>
                <div class="ci-right-panel">
                    <?php
                    $relIds = array_map(function ($plugin) {
                        return $plugin->id;
                    }, $plugins);
                    if (count($relIds) > 0) Reviews::reviewPanel($relIds, SessionUtils::getInstance()->getLogin()["name"] ?? "", true)
                    ?>
                </div>
            <?php } ?>
        </div>
        <?php
    }
}
