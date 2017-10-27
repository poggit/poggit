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
use poggit\release\Release;
use poggit\utils\internet\Mysql;
use poggit\utils\lang\Lang;
use poggit\utils\PocketMineApi;

class SearchPluginsByAuthorPage extends AbstractReleaseListPage {
    private $plugins = [];
    private $title;

    public function __construct(string $param, array $filters) {
        $authors = Lang::explodeNoEmpty(",", $param);
        $wheres = [];
        $type = "";
        $args = [];
        foreach($authors as $authorName) {
            $wheres[] = "rp.owner = ?";
            $type .= "s";
            $args[] = $authorName;
        }
        $where = "(" . implode(" OR ", $wheres) . ")";
        $plugins = Mysql::query("SELECT 
            r.releaseId, r.name, r.version, rp.owner AS author, r.shortDesc, r.projectId AS projectId, r.state AS state,
            r.flags AS flags, r.icon, UNIX_TIMESTAMP(r.creation) AS created, UNIX_TIMESTAMP(r.updateTime) AS updateTime, rp.private AS private,
            p.framework AS framework, res.dlCount AS downloads,  c.category AS cat, s.since AS spoonsince, s.till AS spoontill
            FROM releases r LEFT JOIN releases r2 ON (r.projectId = r2.projectId AND r2.creation > r.creation)
                INNER JOIN projects p ON p.projectId = r.projectId
                INNER JOIN repos rp ON rp.repoId = p.repoId
                INNER JOIN resources res ON res.resourceId = r.artifact
                INNER JOIN release_categories c ON c.projectId = p.projectId
                INNER JOIN release_spoons s ON s.releaseId = r.releaseId
            WHERE r2.releaseId IS NULL AND $where", $type, ...$args);
        if(count($plugins) === 0) {
            throw new MainReleaseListPage(["term" => implode(" ", $authors)], <<<EOM
<p>No plugins by $param found.</p>
EOM
            );
        }

        $session = Session::getInstance();
        $adminlevel = Meta::getAdmlv($session->getName());
        foreach($plugins as $plugin) {
            if($session->getName() == $plugin["author"] ||
                (int) $plugin["state"] >= Config::MIN_PUBLIC_RELEASE_STATE ||
                (int) $plugin["state"] >= Release::STATE_CHECKED && $session->isLoggedIn() ||
                ($adminlevel >= Meta::ADMLV_MODERATOR && (int) $plugin["state"] > Release::STATE_DRAFT)
            ) {
                $thumbNail = new IndexPluginThumbnail();
                $thumbNail->id = (int) $plugin["releaseId"];
                if(isset($this->plugins[$thumbNail->id])) {
                    if(!in_array($plugin["cat"], $this->plugins[$thumbNail->id]->categories)) {
                        $this->plugins[$thumbNail->id]->categories[] = $plugin["cat"];
                    }
                    $this->plugins[$thumbNail->id]->spoons[] = [$plugin["spoonsince"], $plugin["spoontill"]];
                    continue;
                }
                $thumbNail->projectId = (int) $plugin["projectId"];
                $thumbNail->name = $plugin["name"];
                $thumbNail->version = $plugin["version"];
                $thumbNail->author = $plugin["author"];
                $thumbNail->iconUrl = $plugin["icon"];
                $thumbNail->shortDesc = $plugin["shortDesc"];
                $thumbNail->categories[] = $plugin["cat"];
                $thumbNail->spoons[] = [$plugin["spoonsince"], $plugin["spoontill"]];
                $thumbNail->creation = (int) $plugin["created"];
                $thumbNail->updateTime = (int) $plugin["updateTime"];
                $thumbNail->state = (int) $plugin["state"];
                $thumbNail->flags = (int) $plugin["flags"];
                $thumbNail->isPrivate = (int) $plugin["private"];
                $thumbNail->framework = $plugin["framework"];
                $thumbNail->isMine = $session->getName() === $plugin["author"];
                $thumbNail->dlCount = (int) $plugin["downloads"];
                $this->plugins[$thumbNail->id] = $thumbNail;
            }
        }
        $this->title = "Plugins by " . implode(", ", $authors);
    }

    public function getTitle(): string {
        return $this->title;
    }

    public function output() {
        ?>
        <div class="search-header">
            <div class="release-search">
                <div class="resptable-cell">
                    <input type="text" class="release-search-input" id="pluginSearch" placeholder="Search">
                </div>
                <div class="action resptable-cell" id="searchButton">Search Releases</div>
            </div>
            <div class="release-search">
                <div onclick="window.location = '<?= Meta::root() ?>plugins/authors';"
                     class="action resptable-cell">List Authors
                </div>
                <div onclick="window.location = '<?= Meta::root() ?>plugins/categories';"
                     class="action resptable-cell">List Categories
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
                    foreach(Release::$CATEGORIES as $catId => $catName) { ?>
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
        <?php
        $this->listPlugins($this->plugins);
    }
}
