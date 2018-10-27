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

namespace poggit\release\index;

use poggit\account\Session;
use poggit\module\VarPage;
use poggit\release\Release;
use function array_map;
use function in_array;
use function usort;

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

        foreach($plugins as $plugin) {
            $plugin->stats = Release::getReleaseStats($plugin->id, $plugin->projectId, $plugin->creation);
        }

        usort($plugins, function(IndexPluginThumbnail $a, IndexPluginThumbnail $b): int {
            if($a->state === Release::STATE_FEATURED || $b->state === Release::STATE_FEATURED) {
                return -($a->state <=> $b->state);
            }
            return -($a->stats["popularity"] <=> $b->stats["popularity"]);
        });
        ?>
      <div class="plugins-wrapper">
        <div class="ci-right-panel">
          <div class="plugin-index">
            <div id="main-release-list">
                <?php
                $hasProjects = [];
                foreach($plugins as $plugin) {
                    if($firstOnly && isset($hasProjects[$plugin->projectId])) {
                        continue;
                    }
                    $hasProjects[$plugin->projectId] = true;
                    if(!$plugin->isPrivate && !$plugin->parent_releaseId) {
                        Release::pluginPanel($plugin, $plugin->stats);
                    }
                }
                ?>
            </div>
          </div>
        </div>
          <?php if($session->isLoggedIn() && $hasMine) { ?>
            <div class="list-plugins-sidebar">
              <div id="toggle-wrapper" class="release-toggle-wrapper" data-name="My Releases">
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
