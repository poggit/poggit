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

use poggit\Mbd;
use poggit\module\VarPage;
use poggit\Poggit;
use poggit\utils\internet\MysqlUtils;

class ListAuthorsPage extends VarPage {
    private $authors;

    public function __construct(array $params) {
        $sortBy = $params["sort_by"] ?? "plugins";
        if($sortBy === "downloads") {
            $orderBy = "dls";
        } else {
            $orderBy = "cnt";
        }
        $order = "DESC";
        if(isset($params["order"]) and $params["order"] === "asc") {
            $order = "ASC";
        }
        $this->authors = MysqlUtils::query("SELECT repos.owner author, COUNT(DISTINCT releases.projectId) cnt,
                    SUM(art.dlCount) dls FROM releases
                INNER JOIN resources art ON releases.artifact = art.resourceId
                INNER JOIN projects ON releases.projectId = projects.projectId
                INNER JOIN repos ON projects.repoId = repos.repoId
                GROUP BY repos.owner
                HAVING dls > 0
            ORDER BY $orderBy $order");
    }

    public function getTitle(): string {
        return "Most Productive Authors on Poggit";
    }

    public function output() {
        ?>
        <div><h2>Most productive authors on Poggit</h2></div>
        <div><a href="<?= Poggit::getRootPath() ?>pi/authors?sort_by=plugins">Sort by plugins</a></div>
        <div><a href="<?= Poggit::getRootPath() ?>pi/authors?sort_by=downloads">Sort by downloads</a></div>
        <table>
            <tr>
                <th>Author</th>
                <th>Plugins</th>
                <th>Total downloads</th>
            </tr>
            <?php foreach($this->authors as $author) { ?>
                <tr>
                    <td><a href="<?= Poggit::getRootPath() ?>pi/by/<?= $author["author"] ?>">
                            <?php Mbd::displayUser($author["author"], "https://github.com/" . $author["author"] . ".png") ?>
                        </a></td>
                    <td><?= $author["cnt"] ?></td>
                    <td><?= $author["dls"] ?></td>
                </tr>
            <?php } ?>
        </table>
        <?php
    }
}
