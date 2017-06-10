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
use poggit\release\PluginRelease;
use poggit\Config;
use poggit\utils\internet\MysqlUtils;
use poggit\utils\PocketMineApi;

class SearchReleaseListPage extends ListPluginsReleaseListPage {
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

    public function __construct(array $arguments, string $message = "") {
        if(isset($arguments["__path"])) unset($arguments["__path"]);
        $session = SessionUtils::getInstance();

        $this->term = isset($arguments["term"]) ? $arguments["term"] : "";
        $this->name = isset($arguments["term"]) ? "%" . $arguments["term"] . "%" : "%";
        $this->author = isset($arguments["author"]) ? "%" . $arguments["author"] . "%" : $this->name;
        $this->error = isset($arguments["error"]) ? "%" . $arguments["error"] . "%" : "";
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
                $displayedProjects[$thumbNail->projectId] = $thumbNail->id;
            }
        }
    }

    public function getTitle(): string {
        return "PocketMine Plugins";
    }

    public function output() { ?>
        <div class="search-header">
            <div class="release-search">
                <div class="resptable-cell">
                    <input type="text" class="release-search-input" id="pluginSearch" placeholder="Search">
                </div>
                <div class="action resptable-cell" id="searchButton">Search Releases</div>
            </div>
            <div class="release-filter">
                <select id="category-list" onchange="filterReleaseResults()">
                    <option value="0" selected>All Categories</option>
                    <?php
                    foreach(PluginRelease::$CATEGORIES as $catId => $catName) { ?>
                        <option value="<?= $catId ?>"><?= $catName ?></option>
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
        <?php
        $this->listPlugins($this->plugins);
    }
}
