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

use poggit\utils\internet\MysqlUtils;

class PluginsByRepoReleaseListPage extends ListPluginsReleaseListPage {
    private $plugins = [];
    private $semiTitle = "";

    public function __construct(string $query, array $filters) {
        $conds = array_filter(explode(",", $query));
        $wheres = [];
        $type = "";
        $args = [];
        $semiTitlesBy = [];
        $semiTitlesIn = [];
        foreach($conds as $cond) {
            $pieces = array_filter(explode("/", $cond, 2));
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
            r.releaseId, r.name, r.version, rp.owner AS author, r.shortDesc,
            icon.resourceId AS iconId, icon.mimeType AS iconMime, UNIX_TIMESTAMP(r.creation) AS created
            FROM releases r LEFT JOIN releases r2 ON (r.projectId = r2.projectId AND r2.creation > r.creation)
                INNER JOIN projects p ON p.projectId = r.projectId
                INNER JOIN repos rp ON rp.repoId = p.repoId
                INNER JOIN resources icon ON r.icon = icon.resourceId
            WHERE r2.releaseId IS NULL AND $where", $type, ...$args);
        if(count($plugins) === 0) {
            throw new SearchReleaseListPage(["term" => implode(" ", $args)], <<<EOM
<p>No plugins under these repo(s) or by these user(s) found.</p>
EOM
            );
        }
        foreach($plugins as $plugin) {
            $thumbNail = new IndexPluginThumbnail();
            $thumbNail->id = (int) $plugin["releaseId"];
            $thumbNail->name = $plugin["name"];
            $thumbNail->version = $plugin["version"];
            $thumbNail->author = $plugin["author"];
            $thumbNail->iconId = (int) $plugin["iconId"];
            $thumbNail->iconMime = (int) $plugin["iconMime"];
            $thumbNail->shortDesc = $plugin["shortDesc"];
            $thumbNail->creation = (int) $plugin["created"];
            $this->plugins[$thumbNail->id] = $thumbNail;
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
