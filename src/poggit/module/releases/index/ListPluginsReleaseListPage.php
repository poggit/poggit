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
use poggit\module\releases\review\OfficialReviewModule as Reviews;

abstract class ListPluginsReleaseListPage extends VarPage {
    /**
     * @param IndexPluginThumbnail[] $plugins
     */
    protected function listPlugins(array $plugins) {
        $session = SessionUtils::getInstance();
        $adminlevel = $session->isLoggedIn() ? Poggit::getAdmlv($session->getLogin()["name"]) : 0;
        ?>        <div class="release-search">
            <div class="resptable-cell">
                <input type="text" class ="release-search-input" id="pluginSearch" placeholder="Search">
            </div>
            <div class="action resptable-cell" id="searchButton">Search Releases</div>
        </div>
    <div class="plugins-wrapper">
        <div class="plugin-index">
            <?php if(SessionUtils::getInstance()->isLoggedIn()) { ?>
            <div class="listplugins-sidebar">
            <div class="myreleaseswrapper toggle" data-name="My Releases">
            <?php foreach($plugins as $plugin) {
            if ($plugin->isMine) {
                ?>
                <div class="plugin-entry">
                    <div class="plugin-entry-block plugin-icon">
                        <div class ="plugin-image-wrapper">
                        <a href="<?= Poggit::getRootPath() ?>p/<?= htmlspecialchars($plugin->name) ?>/<?= $plugin->id ?>">
                        <?php if($plugin->iconUrl === null) { ?>
                            <img src="<?= Poggit::getRootPath() ?>res/defaultPluginIcon" height="56"/>
                        <?php } else { ?>
                            <img src="<?= $plugin->iconUrl ?>" height="56"/>
                        <?php } ?>
                        </a>
                        </div><div class="smalldate-wrapper">
                        <span class="plugin-smalldate"><?= htmlspecialchars(date('d M Y', $plugin->creation)) ?></span>
                        <span class="plugin-smalldate"><?= $plugin->dlCount ?> download<?= $plugin->dlCount != 1 ? "s" : "" ?></span></div>
                    </div>
                    <div class="plugin-entry-block plugin-main">
                            <a href="<?= Poggit::getRootPath() ?>p/<?= htmlspecialchars($plugin->name) ?>/<?= $plugin->id ?>""><span class="plugin-name"><?= htmlspecialchars($plugin->name) ?></span></a>
                            <span class="plugin-version">Version <?= htmlspecialchars($plugin->version) ?></span>
                            <span class="plugin-author">by <?php EmbedUtils::displayUser($plugin->author) ?></span>
                        <p class="plugin-short-desc"><?= htmlspecialchars($plugin->shortDesc) ?></p>
                        <span class="plugin-state-<?= $plugin->state ?>"><?php echo htmlspecialchars(PluginRelease::$STAGE_HUMAN[$plugin->state]) ?></span>
                    </div>
                </div>                
            <?php } } ?></div></div><?php } ?>
            <div class="mainreleaselist">
                <div id ="searchresults" class="searchresults"></div>
            <?php foreach($plugins as $plugin) {
                if (!$plugin->isMine && (!$plugin->isPrivate && (($plugin->state < PluginRelease::RELEASE_STAGE_PENDING && $plugin->state >= PluginRelease::MIN_PUBLIC_RELSTAGE) || ($plugin->state > PluginRelease::RELEASE_STAGE_DRAFT && $adminlevel >= Poggit::MODERATOR)))) {
                ?>
                <div class="plugin-entry">
                    <div class="plugin-entry-block plugin-icon">
                        <div class ="plugin-image-wrapper">
                        <a href="<?= Poggit::getRootPath() ?>p/<?= htmlspecialchars($plugin->name) ?>/<?= $plugin->id ?>">
                        <?php if($plugin->iconUrl === null) { ?>
                            <img src="<?= Poggit::getRootPath() ?>res/defaultPluginIcon" height="56"/>
                        <?php } else { ?>
                            <img src="<?= $plugin->iconUrl ?>" height="56"/>
                        <?php } ?>
                        </a>   
                        </div>
                        <div class="smalldate-wrapper">
                            <span class="plugin-smalldate"><?= htmlspecialchars(date('d M Y', $plugin->creation)) ?></span>
                            <span class="plugin-smalldate"><?= $plugin->dlCount ?> download<?= $plugin->dlCount != 1 ? "s" : "" ?></span>
                        </div>
                    </div>
                    <div class="plugin-entry-block plugin-main">
                        <p>
                            <a href="<?= Poggit::getRootPath() ?>p/<?= htmlspecialchars($plugin->name) ?>/<?= $plugin->id ?>"><span class="plugin-name"><?= htmlspecialchars($plugin->name) ?></span></a><br />
                            <span class="plugin-version">Version <?= htmlspecialchars($plugin->version) ?></span><br />
                            <span class="plugin-author">by <?php EmbedUtils::displayUser($plugin->author) ?></span>
                        </p>
                        <span class="plugin-state-<?= $plugin->state ?>"><?php echo htmlspecialchars(PluginRelease::$STAGE_HUMAN[$plugin->state]) ?></span>
                    </div>
                </div>
            <?php } } ?>
            </div>
        </div>
        <?php if (Reviews::SHOW_REVIEWS_IN_RELEASE || $adminlevel >= Poggit::MODERATOR) { ?>
        <div class="ci-right-panel">
            <?php
            $relIds = array_map(function($plugin) {
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
