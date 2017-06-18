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
use poggit\Config;
use poggit\Meta;
use poggit\release\PluginRelease;
use poggit\utils\internet\MysqlUtils;
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

    public function __construct(array $arguments, string $message = "") {
        if(isset($arguments["__path"])) unset($arguments["__path"]);
        $session = SessionUtils::getInstance();

        $this->term = isset($arguments["term"]) ? $arguments["term"] : "";
        $this->name = isset($arguments["term"]) ? "%" . $arguments["term"] . "%" : "%";
        $this->author = isset($arguments["author"]) ? "%" . $arguments["author"] . "%" : $this->name;
        if(isset($arguments["cat"])) {
            if(is_numeric($arguments["cat"])) {
                $this->preferCat = (int) $arguments["cat"];
            } else {
                $cat = str_replace(["_", "-"], " ", strtolower($arguments["cat"]));
                foreach(PluginRelease::$CATEGORIES as $catId => $catName) {
                    if($cat === strtolower($catName)) {
                        $this->preferCat = (int) $catId;
                    }
                }
            }
        }
        $this->error = isset($arguments["error"]) ? "%" . $arguments["error"] . "%" : $message;
        $plugins = MysqlUtils::query("SELECT
            r.releaseId, r.projectId AS projectId, r.name, r.version, rp.owner AS author, r.shortDesc, c.category AS cat, s.since AS spoonsince, s.till AS spoontill,
            r.icon, r.state, r.flags, rp.private AS private, res.dlCount AS downloads, p.framework AS framework, UNIX_TIMESTAMP(r.creation) AS created, UNIX_TIMESTAMP(r.updateTime) AS updateTime
            FROM releases r
                INNER JOIN projects p ON p.projectId = r.projectId
                INNER JOIN repos rp ON rp.repoId = p.repoId
                INNER JOIN builds b ON b.buildId = r.buildId
                INNER JOIN resources res ON res.resourceId = r.artifact
                INNER JOIN release_keywords k ON k.projectId = r.projectId
                INNER JOIN release_categories c ON c.projectId = p.projectId
                INNER JOIN release_spoons s ON s.releaseId = r.releaseId
            WHERE (rp.owner = ? OR r.name LIKE ? OR rp.owner LIKE ? OR k.word = ?) ORDER BY r.state DESC, r.updateTime DESC", "ssss",
            $session->getName(), $this->name, $this->author, $this->term);
        foreach($plugins as $plugin) {
            $pluginState = (int) $plugin["state"];
            if($session->getName() == $plugin["author"] || $pluginState >= Config::MIN_PUBLIC_RELEASE_STATE) {
                $thumbNail = new IndexPluginThumbnail();
                $thumbNail->id = (int) $plugin["releaseId"];
                $thumbNail->projectId = (int) $plugin["projectId"];
                if(isset($this->plugins[$thumbNail->id])) {
                    if(!in_array($plugin["cat"], $this->plugins[$thumbNail->id]->categories)) {
                        $this->plugins[$thumbNail->id]->categories[] = $plugin["cat"];
                    }
                    $this->plugins[$thumbNail->id]->spoons[] = [$plugin["spoonsince"], $plugin["spoontill"]];
                    continue;
                }
                $thumbNail->name = $plugin["name"];
                $thumbNail->version = $plugin["version"];
                $thumbNail->author = $plugin["author"];
                $thumbNail->iconUrl = $plugin["icon"];
                $thumbNail->shortDesc = $plugin["shortDesc"];
                $thumbNail->categories[] = $plugin["cat"];
                $thumbNail->spoons[] = [$plugin["spoonsince"], $plugin["spoontill"]];
                $thumbNail->creation = (int) $plugin["created"];
                $thumbNail->state = (int) $plugin["state"];
                $thumbNail->flags = (int) $plugin["flags"];
                $thumbNail->isPrivate = (int) $plugin["private"];
                $thumbNail->framework = $plugin["framework"];
                $thumbNail->isMine = $session->getName() === $plugin["author"];
                $thumbNail->dlCount = (int) $plugin["downloads"];
                $this->plugins[$thumbNail->id] = $thumbNail;
            }
        }
    }

    public function getTitle(): string {
        return strip_tags($this->error ?: "PocketMine Plugins");
    }

    public function output() { ?>
        <?php if($this->error) {
            http_response_code(400); ?>
            <div id="fallback-error"><?= $this->error ?></div>
        <?php } ?>
        <div class="search-header">
            <div class="release-search">
                <div class="resptable-cell">
                    <input type="text" class="release-search-input" id="pluginSearch" placeholder="Search">
                </div>
                <div class="action resptable-cell" id="searchButton">Search Releases</div>
            </div>
            <div class="release-search">
                <div onclick="window.location = '<?= Meta::root() ?>plugins/authors';"
                     class="action resptable-cell">List All Authors
                </div>
                <div onclick="window.location = '<?= Meta::root() ?>plugins/categories';"
                     class="action resptable-cell">List All Categories
                </div>
            </div>
            <div class="release-filter">
                <input id="searchAuthorsQuery" type="text" placeholder="pmmp,poggit-orphanage,sof3"/>
                <div class="resptable-cell">
                    <div class="action" id="searchAuthorsButton">Search by author</div>
                </div>
            </div>
            <div class="release-filter">
                <select id="category-list" onchange="filterReleaseResults()">
                    <option value="0" <?= isset($this->preferCat) ? "" : "selected" ?>>All Categories</option>
                    <?php
                    foreach(PluginRelease::$CATEGORIES as $catId => $catName) { ?>
                        <option <?= isset($this->preferCat) && $this->preferCat === $catId ? "selected" : "" ?>
                                value="<?= $catId ?>"><?= $catName ?></option>
                    <?php }
                    ?>
                </select>
            </div>
            <div class="release-filter">
                <select id="api-list" onchange="filterReleaseResults()">
                    <option value="All API Versions" selected>All API Versions</option>
                    <?php
                    foreach(array_reverse(PocketMineApi::$VERSIONS) as $apiversion => $description) { ?>
                        <option value="<?= $apiversion ?>"><?= $apiversion ?></option>
                    <?php }
                    ?>
                </select>
            </div>
        </div>
        <script>$(document).ready(filterReleaseResults);</script>
        <?php
        $this->listPlugins($this->plugins);
    }
}
