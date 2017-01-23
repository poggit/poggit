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
use poggit\Poggit;
use poggit\resource\ResourceManager;
use poggit\utils\SessionUtils;
use poggit\release\PluginRelease;

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
            r.releaseId, r.name, r.version, rp.owner AS author, r.shortDesc,
            r.icon, r.state, r.flags, rp.private AS private, res.dlCount as downloads, p.framework AS framework, UNIX_TIMESTAMP(r.creation) AS created
            FROM releases r
                INNER JOIN projects p ON p.projectId = r.projectId
                INNER JOIN repos rp ON rp.repoId = p.repoId
                INNER JOIN builds b ON b.buildId = r.buildId
                INNER JOIN resources res ON res.resourceId = b.resourceId
                INNER JOIN release_keywords k ON k.projectId = r.projectId 
            WHERE (rp.owner = ? OR r.name LIKE ? OR rp.owner LIKE ? OR k.word = ?) ORDER BY state DESC", "ssss",
            $session->getLogin()["name"], $this->name, $this->author, $this->term);
        foreach($plugins as $plugin) {
            if ($session->getLogin()["name"] == $plugin["author"] || (int) $plugin["state"] >= PluginRelease::MIN_PUBLIC_RELSTAGE){
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
            $thumbNail->isMine = $session->getLogin()["name"] == $plugin["author"];
            $thumbNail->dlCount = (int) $plugin["downloads"];
            $this->plugins[$thumbNail->id] = $thumbNail;               
            }
        }
    }

    public function getTitle(): string {
        return "Search plugins";
    }

    public function output() {
         $this->listPlugins($this->plugins);
    }
}
