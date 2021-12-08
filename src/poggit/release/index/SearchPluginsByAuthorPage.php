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
use poggit\Meta;
use poggit\module\Module;
use poggit\release\Release;
use poggit\utils\internet\Mysql;
use poggit\utils\lang\Lang;
use function count;
use function implode;
use function in_array;
use const poggit\ASSETS_PATH;

class SearchPluginsByAuthorPage extends AbstractReleaseListPage {
    /** @var IndexPluginThumbnail[] */
    private $plugins = [];
    private $title;

    /**
     * SearchPluginsByAuthorPage constructor.
     *
     * @param string $param
     * @param array  $filters
     * @throws MainReleaseListPage
     */
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
            p.framework AS framework, res.dlCount AS downloads,  c.category AS cat, s.since AS spoonSince, s.till AS spoonTill
            FROM releases r LEFT JOIN releases r2 ON (r.projectId = r2.projectId AND r2.creation > r.creation)
                INNER JOIN projects p ON p.projectId = r.projectId
                INNER JOIN repos rp ON rp.repoId = p.repoId
                INNER JOIN resources res ON res.resourceId = r.artifact
                INNER JOIN release_categories c ON c.projectId = p.projectId
                INNER JOIN release_spoons s ON s.releaseId = r.releaseId
            WHERE r2.releaseId IS NULL AND $where", $type, ...$args);

        $session = Session::getInstance();
        $adminLevel = Meta::getAdmlv($session->getName());
        foreach($plugins as $plugin) {
            if($session->getName() === $plugin["author"] ||
                (int) $plugin["state"] >= Config::MIN_PUBLIC_RELEASE_STATE ||
                ((int) $plugin["state"] >= Release::STATE_CHECKED && $session->isLoggedIn()) ||
                ($adminLevel >= Meta::ADMLV_MODERATOR && (int) $plugin["state"] > Release::STATE_DRAFT)) {
                $thumbNail = new IndexPluginThumbnail();
                $thumbNail->id = (int) $plugin["releaseId"];
                if(isset($this->plugins[$thumbNail->id])) {
                    if(!in_array((int) $plugin["cat"], $this->plugins[$thumbNail->id]->categories, true)) {
                        $this->plugins[$thumbNail->id]->categories[] = (int) $plugin["cat"];
                    }
                    $this->plugins[$thumbNail->id]->spoons[] = [$plugin["spoonSince"], $plugin["spoonTill"]];
                    continue;
                }
                $thumbNail->projectId = (int) $plugin["projectId"];
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
                $thumbNail->dlCount = (int) $plugin["downloads"];
                $this->plugins[$thumbNail->id] = $thumbNail;
            }
        }
        if(count($this->plugins) === 0) {
            throw new MainReleaseListPage(["term" => implode(" ", $authors)], <<<EOM
No plugins by $param found.
EOM
            );
        }
        $this->title = "Plugins by " . implode(", ", $authors);
    }

    public function getTitle(): string {
        return $this->title;
    }

    public function output() {
        ?>
        <?php include ASSETS_PATH . "incl/searchbar.php";
        $this->listPlugins($this->plugins);

        Module::queueJs("jquery.sortElements");
        Module::queueJs("release.list");
    }
}
