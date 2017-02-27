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

namespace poggit\release\index;

use poggit\account\SessionUtils;
use poggit\module\VarPage;
use poggit\release\PluginRelease;

abstract class ListPluginsReleaseListPage extends VarPage {
    /**
     * @param IndexPluginThumbnail[] $plugins
     */
    protected function listPlugins(array $plugins) {
        $session = SessionUtils::getInstance();
        $isMineCount = in_array(true, array_map(function ($plugin) {
            return $plugin->isMine;
        }, $plugins));
        ?>
        <div class="plugins-wrapper">
            <div class="plugin-index">
                <?php if($session->isLoggedIn() && $isMineCount) { ?>
                    <div class="listplugins-sidebar">
                        <div class="myreleaseswrapper toggle" data-name="My Releases">
                            <?php foreach($plugins as $plugin) {
                                if($plugin->isMine) {
                                    PluginRelease::pluginPanel($plugin);
                                }
                            } ?>
                        </div>
                    </div>
                <?php } ?>
                <div class="mainreleaselist">
                    <div id="searchresults" class="searchresults"></div>
                    <?php foreach($plugins as $plugin) {
                        if(!$plugin->isMine && !$plugin->isPrivate) {
                            PluginRelease::pluginPanel($plugin);
                        }
                    } ?>
                </div>
            </div>
        </div>
        <?php
    }
}
