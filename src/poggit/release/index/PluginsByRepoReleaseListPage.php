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
    private $semiTitle = "";

    public function __construct(string $query, array $filters) {
        $conds = array_filter(explode(",", $query), "string_not_empty");
        $wheres = [];
        $type = "";
        $args = [];
        $semiTitlesBy = [];
        $semiTitlesIn = [];
        foreach($conds as $cond) {
            $pieces = array_filter(explode("/", $cond, 2), "string_not_empty");
            if(count($pieces) === 1) {
                $wheres[] = "rp.owner = ?";
                $type .= "s";
                $args[] = $pieces[0];
                $semiTitlesBy[] = $pieces[0];
            } else {
                assert(count($pieces) === 2);
                $wheres[] = "(rp.owner = ? AND rp.name = ?)";
                $type .= "ss";
                $args[] = $pieces[0];
                $args[] = $pieces[1];
                $semiTitlesIn[] = "$pieces[0]/$pieces[1]";
            }
        }
        $where = "(" . implode(" OR ", $wheres) . ")";
        $plugins = MysqlUtils::query("SELECT 
            r.releaseId, r.name, r.version, rp.owner AS author, r.shortDesc, r.projectId AS projectId, r.state AS state, r.flags AS flags,
            r.icon, UNIX_TIMESTAMP(r.creation) AS created, rp.private AS private, p.framework AS framework, res.dlCount AS downloads
            FROM releases r LEFT JOIN releases r2 ON (r.projectId = r2.projectId AND r2.creation > r.creation)
                INNER JOIN projects p ON p.projectId = r.projectId
                INNER JOIN repos rp ON rp.repoId = p.repoId
                INNER JOIN resources res ON res.resourceId = r.artifact
            WHERE r2.releaseId IS NULL AND $where", $type, ...$args);
        if(count($plugins) === 0) {
            throw new SearchReleaseListPage(["term" => implode(" ", $args)], <<<EOM
<p>No plugins under these repo(s) or by these user(s) found.</p>
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
                $result[$thumbNail->id] = $thumbNail;
            }
        }
        $phrases = [];
        if(count($semiTitlesIn) > 0) $phrases[] = "in " . implode(", ", $semiTitlesIn);
        if(count($semiTitlesBy) > 0) $phrases[] = "by " . implode(", ", $semiTitlesBy);
        $this->semiTitle = implode(" or ", $phrases);
    }

    public function getTitle(): string {
        return "Plugins " . $this->semiTitle; // TODO
    }

    public function output() {
        $this->listPlugins($this->plugins);
    }
}
