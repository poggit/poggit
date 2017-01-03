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

use poggit\embed\EmbedUtils;
use poggit\module\VarPage;
use poggit\Poggit;
use poggit\resource\ResourceManager;

abstract class ListPluginsReleaseListPage extends VarPage {
    /**
     * @param IndexPluginThumbnail[] $plugins
     */
    protected function listPlugins(array $plugins) {
        ?>
        <div class="plugin-index">
            <?php foreach($plugins as $plugin) { ?>
                <div class="plugin-entry">
                    <div class="plugin-entry-block plugin-icon">
                        <?php if($plugin->iconId === ResourceManager::NULL_RESOURCE) { ?>
                            <img src="<?= Poggit::getRootPath() ?>res/defaultPluginIcon" height="56"/>
                        <?php } else { ?>
                            <img src="<?= Poggit::getRootPath() ?>r/<?= $plugin->iconId ?>"
                                 data-mimetype="<?= $plugin->iconMime ?>" height="56"/>
                        <?php } ?>
                    </div>
                    <div class="plugin-entry-block plugin-main">
                        <p>
                            <span class="plugin-name"><?= htmlspecialchars($plugin->name) ?></span>
                            <span class="plugin-version">Version <?= htmlspecialchars($plugin->version) ?></span>
                            <span class="plugin-author">by <?php EmbedUtils::displayUser($plugin->author) ?></span>
                        </p>
                        <p class="plugin-short-desc"><?= htmlspecialchars($plugin->shortDesc) ?></p>
                    </div>
                </div>
            <?php } ?>
        </div>
        <?php
    }
}
