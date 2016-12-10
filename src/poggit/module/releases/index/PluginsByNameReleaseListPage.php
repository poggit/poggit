<?php

/*
 * Poggit
 *
 * Copyright (C) 2016 Poggit
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

use poggit\Poggit;
use poggit\utils\MysqlUtils;

class PluginsByNameReleaseListPage extends ListPluginsReleaseListPage {
    /** @var IndexPluginThumbnail[] */
    private $plugins = [];

    /** @var string */
    private $name;

    public function __construct(string $name) {
        $this->name = $name;
        $plugins = MysqlUtils::query("SELECT 
            r.releaseId, r.name, r.version, rp.owner AS author, r.shortDesc,
            icon.resourceId AS iconId, icon.mimeType AS iconMime, UNIX_TIMESTAMP(r.creation) AS created
            FROM releases r LEFT JOIN releases r2 ON (r.projectId = r2.projectId AND r2.creation > r.creation)
                INNER JOIN projects p ON p.projectId = r.projectId
                INNER JOIN repos rp ON rp.repoId = p.repoId
                INNER JOIN resources icon ON r.icon = icon.resourceId
            WHERE r2.releaseId IS NULL AND r.name = ?", "s", $name);
        if(count($plugins) === 1) Poggit::redirect("p/$name");
        $html = htmlspecialchars($name);
        if(count($plugins) === 0) {
            throw new SearchReleaseListPage(["term" => $name], <<<EOM
<p>There are no plugins called $html.</p>
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
    }

    public function getTitle(): string {
        return "Plugins called " . htmlspecialchars($this->name);
    }

    public function output() {
        $this->listPlugins($this->plugins);
    }
}
