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

use poggit\Meta;
use poggit\module\VarPage;
use poggit\release\Release;
use poggit\utils\internet\Mysql;
use function rawurlencode;
use function strtolower;

class ListCategoriesPage extends VarPage {
    private $cats = [];

    public function __construct() {
        $rows = Mysql::query("SELECT category, IF(isMainCategory, 1, 0) isMain, COUNT(*) cnt FROM release_categories
                INNER JOIN (SELECT DISTINCT projectId FROM releases WHERE state >= ?) r ON r.projectId = release_categories.projectId
                GROUP BY category, isMainCategory", "i", Release::STATE_CHECKED);
        foreach(Release::$CATEGORIES as $catId => $catName) {
            $this->cats[$catId] = ["name" => $catName, "major" => 0, "minor" => 0];
        }
        foreach($rows as $row) {
            $this->cats[(int) $row["category"]][((int) $row["isMain"]) ? "major" : "minor"] = (int) $row["cnt"];
        }
    }

    public function getTitle(): string {
        return "Categories";
    }

    public function output() {
        ?>
      <table>
        <tr>
          <th>Category</th>
          <th>Major category of plugins</th>
          <th>Minor category of plugins</th>
        </tr>
          <?php foreach($this->cats as $catId => $cat) { ?>
            <tr>
              <td>
                <a href="<?= Meta::root() ?>plugins?cat=<?= strtolower(rawurlencode($cat["name"])) ?>">
                    <?= $cat["name"] ?></a>
              </td>
              <td><?= $cat["major"] ?></td>
              <td><?= $cat["minor"] ?></td>
              <td><a class="action" href="https://github.com/poggit/plugins/issues/<?= $catId ?>" target="_blank">
                  Subscribe</a></td>
            </tr>
          <?php } ?>
      </table>
        <?php
    }
}
