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
use poggit\Config;
use poggit\Meta;
use poggit\module\Module;
use poggit\module\VarPageModule;
use poggit\release\Release;
use poggit\utils\internet\Mysql;
use poggit\utils\PocketMineApi;

class MainReleaseListPage extends AbstractReleaseListPage {
    /** @var IndexPluginThumbnail[] */
    private $plugins = [];
    /** @var string */
    private $author;
    /** @var string */
    private $name;
    /** @var string */
    private $term;
    /** @var string */
    private $error;
    /** @var int|null */
    private $preferCat;

    /** @var int */
    private $checkedPlugins;

    public function __construct(array $arguments, string $message = "") {
        if(isset($arguments["__path"])) unset($arguments["__path"]);
        $session = Session::getInstance();

        $this->term = $arguments["term"] ?? "";
        $this->name = isset($arguments["term"]) ? "%" . $arguments["term"] . "%" : "%";
        $this->author = isset($arguments["author"]) ? "%" . $arguments["author"] . "%" : $this->name;
        if(isset($arguments["cat"])) {
            if(is_numeric($arguments["cat"])) {
                $this->preferCat = (int) $arguments["cat"];
            } else {
                $cat = str_replace(["_"], " ", strtolower($arguments["cat"]));
                foreach(Release::$CATEGORIES as $catId => $catName) {
                    if($cat === strtolower($catName)) {
                        $this->preferCat = (int) $catId;
                    }
                }
            }
        }
        $this->error = $arguments["error"] ?? $message;
        $plugins = Mysql::query("SELECT
            r.releaseId, r.projectId AS projectId, r.name, r.version, rp.owner AS author, r.shortDesc, c.category AS cat, s.since AS spoonsince, s.till AS spoontill, r.parent_releaseId,
            r.icon, r.state, r.flags, rp.private AS private, res.dlCount AS downloads, p.framework AS framework,
            IFNULL(rev.scoreTotal, 0) scoreTotal, IFNULL(rev.scoreCount, 0) scoreCount,
            UNIX_TIMESTAMP(r.creation) AS created, UNIX_TIMESTAMP(r.updateTime) AS updateTime,
            (SELECT SUM(dlCount) FROM releases INNER JOIN resources ON resources.resourceId = releases.artifact
                WHERE releases.projectId = r.projectId) totalDl
            FROM releases r
                INNER JOIN projects p ON p.projectId = r.projectId
                INNER JOIN repos rp ON rp.repoId = p.repoId
                INNER JOIN builds b ON b.buildId = r.buildId
                INNER JOIN resources res ON res.resourceId = r.artifact
                INNER JOIN release_keywords k ON k.projectId = r.projectId
                INNER JOIN release_categories c ON c.projectId = p.projectId
                INNER JOIN release_spoons s ON s.releaseId = r.releaseId
                LEFT JOIN (SELECT releaseId, SUM(score) scoreTotal, COUNT(*) scoreCount FROM release_reviews GROUP BY releaseId) rev
                    ON rev.releaseId = r.releaseId
            WHERE (rp.owner = ? OR r.name LIKE ? OR rp.owner LIKE ? OR k.word = ?) AND (flags & ?) = 0
            ORDER BY r.state = ? DESC, r.creation DESC", "ssssii",
            $session->getName(), $this->name, $this->author, $this->term, Release::FLAG_OBSOLETE, Release::STATE_FEATURED);
        foreach($plugins as $plugin) {
            $pluginState = (int) $plugin["state"];
            if($session->getName() === $plugin["author"] || $pluginState >= Config::MIN_PUBLIC_RELEASE_STATE) {
                $thumbNail = new IndexPluginThumbnail();
                $thumbNail->id = (int) $plugin["releaseId"];
                if(isset($this->plugins[$thumbNail->id])) {
                    if(!in_array((int) $plugin["cat"], $this->plugins[$thumbNail->id]->categories, true)) {
                        $this->plugins[$thumbNail->id]->categories[] = (int) $plugin["cat"];
                    }
                    $this->plugins[$thumbNail->id]->spoons[] = [$plugin["spoonsince"], $plugin["spoontill"]];
                    continue;
                }
                $thumbNail->projectId = (int) $plugin["projectId"];
                $thumbNail->parent_releaseId = (int) $plugin["parent_releaseId"];
                $thumbNail->name = $plugin["name"];
                $thumbNail->version = $plugin["version"];
                $thumbNail->author = $plugin["author"];
                $thumbNail->iconUrl = $plugin["icon"];
                $thumbNail->shortDesc = $plugin["shortDesc"];
                $thumbNail->categories[] = (int) $plugin["cat"];
                $thumbNail->spoons[] = [$plugin["spoonsince"], $plugin["spoontill"]];
                $thumbNail->creation = (int) $plugin["created"];
                $thumbNail->updateTime = (int) $plugin["updateTime"];
                $thumbNail->state = (int) $plugin["state"];
                $thumbNail->flags = (int) $plugin["flags"];
                $thumbNail->isPrivate = (int) $plugin["private"];
                $thumbNail->framework = $plugin["framework"];
                $thumbNail->isMine = $session->getName() === $plugin["author"];
                $thumbNail->dlCount = (int) $plugin["downloads"];
                $thumbNail->scoreCount = (int) $plugin["scoreCount"];
                $thumbNail->scoreTotal = (int) $plugin["scoreTotal"];
                $thumbNail->totalDl = (int) $plugin["totalDl"];
                $this->plugins[$thumbNail->id] = $thumbNail;
            }
        }

        $this->checkedPlugins = (int) Mysql::query("SELECT IFNULL(COUNT(*), 0) cnt
                FROM (SELECT r.releaseId, MAX(till) api FROM releases r
                    LEFT JOIN release_spoons ON r.releaseId = release_spoons.releaseId
                    WHERE state = ?
                    GROUP BY r.releaseId) t
                INNER JOIN known_spoons ks ON ks.name = t.api
                WHERE ks.id >= (SELECT ks2.id FROM known_spoons ks2 WHERE ks2.name = ?)",
            "is",Release::STATE_CHECKED, PocketMineApi::LATEST_COMPAT)[0]["cnt"];
    }

    public function getTitle(): string {
        return strip_tags($this->error ?: "PocketMine Plugins");
    }

    public function output() { ?>
        <div class="togglebar-wrapper">
            <div class="togglebar">
                <button class="navbar-toggle" type="button" data-toggle="collapse" data-target="#search-nav" aria-controls="search-nav" aria-expanded="false" aria-label="Toggle Search and Sorting">
                    <img onclick="$('html, body').animate({scrollTop: 0},500);" class="sidesearch-btn" src="<?= Meta::root() ?>res/search-icon.png"/>
                </button>
            </div>
        <nav class="search-nav collapse navbar-default" role="navigation" id="search-nav">
            <div class="search-header">
                <div class="release-search">
                    <div class="resptable-cell">
                        <input type="text" class="release-search-input" id="pluginSearch" placeholder="Search Releases" size="20">
                    </div>
                  <select id="pluginSearchField">
                    <option value="p/" selected>Plugin</option>
                    <option value="plugins/by/">Author</option>
                  </select>
                    <div class="action resptable-cell" id="searchButton">Search</div>
                </div>
                <div class="release-list-buttons">
                    <div onclick="window.location = '<?= Meta::root() ?>plugins/authors';"
                         class="action resptable-cell">List Authors
                    </div>
                    <div onclick="window.location = '<?= Meta::root() ?>plugins/categories';"
                         class="action resptable-cell">List Categories
                    </div>
                </div>
                <div class="release-filter">
                    <select id="category-list" class="release-filter-select">
                        <option value="0" <?= isset($this->preferCat) ? "" : "selected" ?>>All Categories</option>
                        <?php
                        foreach(Release::$CATEGORIES as $catId => $catName) { ?>
                            <option <?= isset($this->preferCat) && $this->preferCat === $catId ? "selected" : "" ?>
                                    value="<?= $catId ?>"><?= $catName ?></option>
                        <?php }
                        ?>
                    </select>
                </div>
                <div class="release-filter">
                    <select id="api-list" class="release-filter-select">
                        <option value="All API Versions" selected>All API Versions</option>
                        <?php
                        foreach(array_reverse(PocketMineApi::$VERSIONS) as $apiversion => $description) { ?>
                            <option value="<?= $apiversion ?>"><?= $apiversion ?></option>
                        <?php }
                        ?>
                    </select>
                </div>
                <div class="release-filter action" id="release-sort-button">Sort</div>
            </div>
            <div style="display: none;" id="release-sort-dialog" title="Sort releases">
                <ol id="release-sort-list">
                    <li class="release-sort-row release-sort-row-template">
                        <select class="release-sort-category">
                            <option value="state-change-date">Date featured/approved/voted</option>
                            <option value="submit-date">Date submitted (latest version)</option>
                            <!--                <option value="submit-date-first">Date submitted (first version)</option>-->
                            <option value="state">Featured > Approved > Voted</option>
                            <option value="total-downloads">Downloads (total)</option>
                            <option value="downloads">Downloads (latest version)</option>
                            <option value="mean-review">Average review score (latest version)</option>
                            <option value="name">Plugin name</option>
                        </select>
                        <select class="release-sort-direction">
                            <option value="asc">Ascending</option>
                            <option value="desc" selected>Descending</option>
                        </select>
                        <span class="action release-sort-row-close">&cross;</span>
                    </li>
                </ol>
                <span class="action" id="release-sort-row-add">+</span>
            </div>
        </nav>
        </div>
        <?php if($this->error) {
            http_response_code(400); ?>
            <div id="fallback-error"><?= $this->error ?></div>
        <?php } ?>
        <?php
        $this->listPlugins($this->plugins);
        if($this->checkedPlugins > 0) {
            if(Session::getInstance()->isLoggedIn()) { ?>
              <div class="plugin-count">
                <h5><?= $this->checkedPlugins ?> release<?= $this->checkedPlugins === 1 ? " is " : "s are " ?>awaiting
                  approval.
                  <a href="<?= Meta::root() ?>review">Have a look</a> and approve/reject plugins yourself!</h5>
              </div>
            <?php } else { ?>
              <div class="plugin-count"><h5><a href="<?= Meta::root() ?>login">Login</a> to
                  see <?= $this->checkedPlugins ?>
                  more releases!</h5></div>
                <?php
            }
        }
        Module::queueJs("jquery.sortElements");
        Module::queueJs("release.list");
    }
}
