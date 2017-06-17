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
use poggit\Poggit;
use poggit\release\PluginRelease;
use poggit\utils\internet\MysqlUtils;

class PluginsByRepoReleaseListPage extends ListPluginsReleaseListPage {
    private $plugins = [];
    private $title;

    public function __construct(string $param, array $filters) {
        $authors = array_filter(explode(",", $param), "string_not_empty");
        $wheres = [];
        $type = "";
        $args = [];
        foreach($authors as $authorName) {
            $wheres[] = "rp.owner = ?";
            $type .= "s";
            $args[] = $authorName;
        }
        $where = "(" . implode(" OR ", $wheres) . ")";
        $plugins = MysqlUtils::query("SELECT 
            r.releaseId, r.name, r.version, rp.owner AS author, r.shortDesc, r.projectId AS projectId, r.state AS state,
            r.flags AS flags, r.icon, UNIX_TIMESTAMP(r.creation) AS created, rp.private AS private,
            p.framework AS framework, res.dlCount AS downloads
            FROM releases r LEFT JOIN releases r2 ON (r.projectId = r2.projectId AND r2.creation > r.creation)
                INNER JOIN projects p ON p.projectId = r.projectId
                INNER JOIN repos rp ON rp.repoId = p.repoId
                INNER JOIN resources res ON res.resourceId = r.artifact
            WHERE r2.releaseId IS NULL AND $where", $type, ...$args);
        if(count($plugins) === 0) {
            throw new MainReleaseListPage(["term" => implode(" ", $authors)], <<<EOM
<p>No plugins by $param found.</p>
EOM
            );
        }

        $session = SessionUtils::getInstance();
        $adminlevel = Poggit::getUserAccess($session->getName());
        foreach($plugins as $plugin) {
            if($session->getName() == $plugin["author"] ||
                (int) $plugin["state"] >= Config::MIN_PUBLIC_RELEASE_STATE ||
                (int) $plugin["state"] >= PluginRelease::RELEASE_STATE_CHECKED && $session->isLoggedIn() ||
                ($adminlevel >= Poggit::MODERATOR && (int) $plugin["state"] > PluginRelease::RELEASE_STATE_DRAFT)
            ) {
                $thumbNail = new IndexPluginThumbnail();
                $thumbNail->id = (int) $plugin["releaseId"];
                $thumbNail->projectId = (int) $plugin["projectId"];
                $thumbNail->name = $plugin["name"];
                $thumbNail->version = $plugin["version"];
                $thumbNail->author = $plugin["author"];
                $thumbNail->iconUrl = $plugin["icon"];
                $thumbNail->shortDesc = $plugin["shortDesc"];
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
        $this->title = "Plugins by " . implode(", ", $authors);
    }

    public function getTitle(): string {
        return $this->title;
    }

    public function output() {
        $this->listPlugins($this->plugins);
    }
}
