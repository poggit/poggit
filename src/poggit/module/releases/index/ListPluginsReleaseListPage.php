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
use poggit\release\PluginRelease;
use poggit\utils\SessionUtils;

abstract class ListPluginsReleaseListPage extends VarPage {
    /**
     * @param IndexPluginThumbnail[] $plugins
     */
    protected function listPlugins(array $plugins) {
        ?>
        <div class="release-search">
            <div class="resptable-cell">
                <input type="text" class ="release-search-input" id="pluginSearch" placeholder="Search">
            </div>
            <div class="action resptable-cell" id="searchButton">Search Releases</div>
        </div>
        <div class="plugin-index">
            <?php if(SessionUtils::getInstance()->isLoggedIn()) { ?>
            <div class="listplugins-sidebar">
            <div class="myreleaseswrapper toggle" data-name="My Releases">
            <?php foreach($plugins as $plugin) {
            if ($plugin->isMine) {
                ?>
                <div class="plugin-entry">
                    <div class="plugin-entry-block plugin-icon">
                        <a href="<?= Poggit::getRootPath() ?>p/<?= htmlspecialchars($plugin->name) ?>/<?= $plugin->id ?>">
                        <?php if($plugin->iconUrl === null) { ?>
                            <img src="<?= Poggit::getRootPath() ?>res/defaultPluginIcon" height="56"/>
                        <?php } else { ?>
                            <img src="<?= $plugin->iconUrl ?>" height="56"/>
                        <?php } ?>
                        </a>
                    </div>
                    <div class="plugin-entry-block plugin-main">
                        <p>
                            <a href="<?= Poggit::getRootPath() ?>p/<?= htmlspecialchars($plugin->name) ?>/<?= $plugin->id ?>""><span class="plugin-name"><?= htmlspecialchars($plugin->name) ?></span></a>
                            <span class="plugin-version">Version <?= htmlspecialchars($plugin->version) ?></span>
                            <span class="plugin-author">by <?php EmbedUtils::displayUser($plugin->author) ?></span>
                        </p>
                        <p class="plugin-short-desc"><?= htmlspecialchars($plugin->shortDesc) ?></p>
                        <span class="plugin-state-<?= $plugin->state ?>"><span class="plugin-short-desc"><?= htmlspecialchars(date('d M Y', $plugin->creation)) ?></span> <?php echo htmlspecialchars(PluginRelease::$STAGE_HUMAN[$plugin->state]) ?></span>
                    </div>
                </div>                
            <?php } } ?><hr /></div></div><?php } ?>
            <div class="mainreleaselist">
                <div id ="searchresults" class="searchresults"></div>
            <?php foreach($plugins as $plugin) {
                if (!$plugin->isMine && (!$plugin->isPrivate && $plugin->state > 0)) {
                ?>
                <div class="plugin-entry">
                    <div class="plugin-entry-block plugin-icon">
                        <a href="<?= Poggit::getRootPath() ?>p/<?= htmlspecialchars($plugin->name) ?>/<?= $plugin->id ?>">
                        <?php if($plugin->iconUrl === null) { ?>
                            <img src="<?= Poggit::getRootPath() ?>res/defaultPluginIcon" height="56"/>
                        <?php } else { ?>
                            <img src="<?= $plugin->iconUrl ?>" height="56"/>
                        <?php } ?>
                        </a>
                    </div>
                    <div class="plugin-entry-block plugin-main">
                        <p>
                            <a href="<?= Poggit::getRootPath() ?>p/<?= htmlspecialchars($plugin->name) ?>/<?= $plugin->id ?>"><span class="plugin-name"><?= htmlspecialchars($plugin->name) ?></span></a>
                            <span class="plugin-version">Version <?= htmlspecialchars($plugin->version) ?></span>
                            <span class="plugin-author">by <?php EmbedUtils::displayUser($plugin->author) ?></span>
                        </p>
                        <p class="plugin-short-desc"><?= htmlspecialchars($plugin->shortDesc) ?></p>
                        <span class="plugin-state-<?= $plugin->state ?>"><span class="plugin-short-desc"><?= htmlspecialchars(date('d M Y', $plugin->creation)) ?></span> <?php echo htmlspecialchars(PluginRelease::$STAGE_HUMAN[$plugin->state]) ?></span>
                    </div>
                </div>
            <?php } } ?>
            </div>
        </div>
        </div>
        <?php
    }
}
