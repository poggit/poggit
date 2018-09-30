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

use poggit\Config;
use poggit\Mbd;
use poggit\Meta;
use poggit\module\VarPage;
use poggit\utils\internet\Mysql;
use function in_array;
use function sprintf;

class ListAuthorsPage extends VarPage {
    private $authors;
    private $sort1, $order1, $sort2, $order2;

    public function __construct(array $params) {
        $this->sort1 = $_REQUEST["sort_1"] ?? "dls";
        if(!in_array($this->sort1, ["dls", "cnt", "dpp"], true)) $this->sort1 = "dls";
        $this->order1 = $_REQUEST["order_1"] ?? "desc";
        if(!in_array($this->order1, ["asc", "desc"], true)) $this->order1 = "desc";
        $this->sort2 = $_REQUEST["sort_2"] ?? "dpp";
        if(!in_array($this->sort2, ["dls", "cnt", "dpp"], true)) $this->sort2 = "dpp";
        $this->order2 = $_REQUEST["order_2"] ?? "desc";
        if(!in_array($this->order2, ["asc", "desc"], true)) $this->order2 = "desc";
        $this->authors = Mysql::query("SELECT author, cnt, dls, dls/cnt dpp
            FROM (SELECT repos.owner author, COUNT(DISTINCT releases.projectId) cnt,
                    SUM(art.dlCount) dls FROM releases
                INNER JOIN resources art ON releases.artifact = art.resourceId
                INNER JOIN projects ON releases.projectId = projects.projectId
                INNER JOIN repos ON projects.repoId = repos.repoId
                WHERE state >= ?
                GROUP BY repos.owner
                HAVING dls > 0) t
            ORDER BY $this->sort1 $this->order1, $this->sort2 $this->order2", "i", Config::MIN_PUBLIC_RELEASE_STATE);
    }

    public function getTitle(): string {
        return "Most Productive Authors on Poggit";
    }

    public function output() {
        ?>
      <div><h2>Most productive authors on Poggit</h2></div>
      <form name="sort" method="get">
        <div>Sort by:</div>
        <div class="sort-section">
          1. <select name="sort_1" class="author-select">
            <option value="cnt" <?= $this->sort1 === "cnt" ? "selected" : "" ?>>Plugins</option>
            <option value="dls" <?= $this->sort1 === "dls" ? "selected" : "" ?>>Total Downloads</option>
            <option value="dpp" <?= $this->sort1 === "dpp" ? "selected" : "" ?>>Downloads per Plugin</option>
          </select><label>in</label><select name="order_1" class="author-select">
            <option value="asc" <?= $this->order1 === "asc" ? "selected" : "" ?>>Ascending</option>
            <option value="desc" <?= $this->order1 === "desc" ? "selected" : "" ?>>Descending</option>
          </select><label>order</label>
        </div>
        <div class="sort-section">
          2. <select name="sort_2" class="author-select">
            <option value="cnt" <?= $this->sort2 === "cnt" ? "selected" : "" ?>>Plugins</option>
            <option value="dls" <?= $this->sort2 === "dls" ? "selected" : "" ?>>Total Downloads</option>
            <option value="dpp" <?= $this->sort2 === "dpp" ? "selected" : "" ?>>Downloads per Plugin</option>
          </select><label>in</label><select name="order_2" class="author-select">
            <option value="asc" <?= $this->order2 === "asc" ? "selected" : "" ?>>Ascending</option>
            <option value="desc" <?= $this->order2 === "desc" ? "selected" : "" ?>>Descending</option>
          </select><label>order</label>
        </div>
        <input type="submit" value="Sort Plugins" class="action"/>
      </form>
      <table>
        <tr>
          <th>Author</th>
          <th>Plugins</th>
          <th>D/L Total</th>
          <th>D/L per Plugin</th>
        </tr>
          <?php foreach($this->authors as $author) { ?>
            <tr>
              <td><a href="<?= Meta::root() ?>plugins/by/<?= $author["author"] ?>">
                      <?php Mbd::displayUser($author["author"], "https://github.com/" . $author["author"] . ".png") ?>
                </a></td>
              <td><?= $author["cnt"] ?></td>
              <td><?= $author["dls"] ?></td>
              <td><?= sprintf("%g", $author["dpp"]) ?></td>
            </tr>
          <?php } ?>
      </table>
        <?php
    }
}
