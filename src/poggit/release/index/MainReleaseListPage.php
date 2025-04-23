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
use poggit\Config;
use poggit\Mbd;
use poggit\module\Module;
use poggit\release\Release;
use poggit\utils\PocketMineApi;
use poggit\utils\internet\Mysql;
use function http_response_code;
use function in_array;
use function is_numeric;
use function str_replace;
use function strip_tags;
use function strtolower;
use const poggit\ASSETS_PATH;

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
    /** @var string|null */
    private $noAltApi = false;
    /** @var string|null */
    private $altApiBanner = null;

    /** @var int */
    private $checkedPlugins;

    public function __construct(array $arguments, string $message = "") {
        if(isset($arguments["__path"])) {
            unset($arguments["__path"]);
        }
        $session = Session::getInstance();

        $this->term = $arguments["term"] ?? "";
        $this->name = isset($arguments["term"]) ? "%" . $arguments["term"] . "%" : "%";
        $this->author = isset($arguments["author"]) ? "%" . $arguments["author"] . "%" : $this->name;
        if(isset($arguments["cat"])) {
            if(is_numeric($arguments["cat"])) {
                $this->preferCat = (int) $arguments["cat"];
            } else {
                $cat = str_replace("_", " ", strtolower($arguments["cat"]));
                foreach(Release::$CATEGORIES as $catId => $catName) {
                    if($cat === strtolower($catName)) {
                        $this->preferCat = (int) $catId;
                    }
                }
            }
        }
        if(isset($arguments["api"])) {
            $this->preferApi = $arguments["api"];
        }

        $lastSupported = null;
        $lastSupportedIncompatible = null;
        foreach(PocketMineApi::$VERSIONS as $version => $description) {
            if($description["incompatible"]) {
                $lastSupportedIncompatible = $lastSupported;
            }
            $lastSupported = $version;
        }

        $this->altApiBanner = isset($arguments["api"]) ? null : $lastSupportedIncompatible;
        $this->noAltApi = $lastSupportedIncompatible === null;

        $this->error = $arguments["error"] ?? $message;
        $outdatedFilter = Release::FLAG_OUTDATED;
        if(isset($_GET["outdated"])) {
            $outdatedFilter = 0;
        }
        $plugins = Mysql::query("SELECT
            r.releaseId, r.projectId AS projectId, r.name, r.version, rp.owner AS author, r.shortDesc, c.category AS cat, s.since AS spoonSince, s.till AS spoonTill, r.parent_releaseId,
            r.icon, r.state, r.flags, rp.private AS private, p.framework AS framework,
            UNIX_TIMESTAMP(r.creation) AS created, UNIX_TIMESTAMP(r.updateTime) AS updateTime
            FROM releases r
                INNER JOIN projects p ON p.projectId = r.projectId
                INNER JOIN repos rp ON rp.repoId = p.repoId
                INNER JOIN builds b ON b.buildId = r.buildId
                INNER JOIN release_keywords k ON k.projectId = r.projectId
                INNER JOIN release_categories c ON c.projectId = p.projectId
                INNER JOIN release_spoons s ON s.releaseId = r.releaseId
                INNER JOIN resources ar ON ar.resourceId = r.artifact
            WHERE (rp.owner = ? OR r.name LIKE ? OR rp.owner LIKE ? OR k.word = ?) AND (flags & ?) = 0", "ssssi",
            $session->getName(), $this->name, $this->author, $this->term, Release::FLAG_OBSOLETE | $outdatedFilter);
        foreach($plugins as $plugin) {
            $pluginState = (int) $plugin["state"];
            if($session->getName() === $plugin["author"] || $pluginState >= Config::MIN_PUBLIC_RELEASE_STATE) {
                $thumbNail = new IndexPluginThumbnail();
                $thumbNail->id = (int) $plugin["releaseId"];
                if(isset($this->plugins[$thumbNail->id])) {
                    if(!in_array((int) $plugin["cat"], $this->plugins[$thumbNail->id]->categories, true)) {
                        $this->plugins[$thumbNail->id]->categories[] = (int) $plugin["cat"];
                    }
                    if(!in_array([$plugin["spoonSince"], $plugin["spoonTill"]], $this->plugins[$thumbNail->id]->spoons, true)) {
                        $this->plugins[$thumbNail->id]->spoons[] = [$plugin["spoonSince"], $plugin["spoonTill"]];
                    }
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
                $thumbNail->spoons[] = [$plugin["spoonSince"], $plugin["spoonTill"]];
                $thumbNail->creation = (int) $plugin["created"];
                $thumbNail->updateTime = (int) $plugin["updateTime"];
                $thumbNail->state = (int) $plugin["state"];
                $thumbNail->flags = (int) $plugin["flags"];
                $thumbNail->isPrivate = (int) $plugin["private"];
                $thumbNail->framework = $plugin["framework"];
                $thumbNail->isMine = $session->getName() === $plugin["author"];

                $this->plugins[$thumbNail->id] = $thumbNail;
            }
        }
    }

    public function getTitle(): string {
        return strip_tags($this->error ?: "PocketMine Plugins");
    }

    public function output() {
        include ASSETS_PATH . "incl/searchbar.php";
        if($this->altApiBanner !== null) {
            ?>
                <div class="alert alert-info">
                    Looking for plugins for API <?= $this->altApiBanner ?> instead? Click
                    <a href="/plugins?api=<?= $this->altApiBanner ?>&outdated">here</a>.
                </div>
            <?php
        } elseif(!$this->noAltApi) {
            ?>
                <div class="alert alert-info">
                    Looking for plugins for API <?= PocketMineAPI::$PROMOTED ?> instead? Click
                    <a href="/plugins">here</a>.
                </div>
            <?php
        }
        ?>
        <div class="alert alert-warning" id="no-plugins" style="display: none;">
            No plugins could be found, try adjusting the search parameters.
        </div>
      <?php
        if($this->error) {
            http_response_code(404); ?>
          <div id="fallback-error"><?= Mbd::esq($this->error) ?></div>
        <?php }
        $this->listPlugins($this->plugins, false);
        Module::queueJs("jquery.sortElements");
        Module::queueJs("release.list");
    }
}
