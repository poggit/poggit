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

use poggit\account\Session;
use poggit\module\VarPage;
use poggit\release\Release;

abstract class AbstractReleaseListPage extends VarPage {
    /**
     * @param IndexPluginThumbnail[] $plugins
     * @param bool                   $firstOnly
     */
    protected function listPlugins(array $plugins, bool $firstOnly = true) {
        $session = Session::getInstance();
        $hasMine = in_array(true, array_map(function($plugin) {
            return $plugin->isMine;
        }, $plugins), true);
        ?>
        <div class="plugins-wrapper">
            <div class="ci-rightpanel">
                <div class="plugin-index">
                    <div class="mainreleaselist" id="mainreleaselist">
                        <?php
                        $hasProjects = [];
                        foreach($plugins as $plugin) {
                            if($firstOnly && isset($hasProjects[$plugin->projectId])) continue;
                            $hasProjects[$plugin->projectId] = true;
                            if(!$plugin->isMine && !$plugin->isPrivate && !$plugin->parent_releaseId) {
                                Release::pluginPanel($plugin);
                            }
                        }
                        ?>
                    </div>
                </div>
            </div>
            <?php if($hasMine && $session->isLoggedIn()) { ?>
                <div class="listplugins-sidebar">
                    <div id="togglewrapper" class="release-togglewrapper" data-name="My Releases">
                        <?php foreach($plugins as $plugin) {
                            if($plugin->isMine) {
                                Release::pluginPanel($plugin);
                            }
                        } ?>
                    </div>
                </div>
            <?php } ?>
        </div>
        <?php
    }
}
