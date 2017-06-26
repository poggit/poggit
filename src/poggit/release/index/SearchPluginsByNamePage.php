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
use poggit\Meta;
use poggit\utils\internet\MysqlUtils;

class SearchPluginsByNamePage extends AbstractReleaseListPage {
    /** @var IndexPluginThumbnail[] */
    private $plugins = [];

    /** @var string */
    private $name;

    public function __construct(string $name) {
        $this->name = $name;
        $plugins = MysqlUtils::query("SELECT 
            r.releaseId, r.name, r.version, rp.owner AS author, r.shortDesc, p.projectId, r.icon, r.state, r.flags,
            rp.private AS private, p.framework AS framework, UNIX_TIMESTAMP(r.creation) AS created
            FROM releases r LEFT JOIN releases r2 ON (r.projectId = r2.projectId AND r2.creation > r.creation)
                INNER JOIN projects p ON p.projectId = r.projectId
                INNER JOIN repos rp ON rp.repoId = p.repoId
            WHERE r2.releaseId IS NULL AND r.name = ?", "s", "%$name%");
        if(count($plugins) === 1) Meta::redirect("p/$name");
        $html = htmlspecialchars($name);
        if(count($plugins) === 0) {
            throw new MainReleaseListPage(["term" => $name], <<<EOM
<p>There are no plugins called $html.</p>
EOM
            );
        }
        $hasProjects = [];
        foreach($plugins as $plugin) {
            $projectId = $plugin["projectId"];
            if(isset($hasProjects[$projectId])) continue;
            $hasProjects[$projectId] = true;
            $thumbNail = new IndexPluginThumbnail();
            $thumbNail->id = (int) $plugin["releaseId"];
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
            $thumbNail->isMine = SessionUtils::getInstance()->getName() == $plugin["author"];
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
