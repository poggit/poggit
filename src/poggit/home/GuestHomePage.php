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

namespace poggit\home;

use poggit\ci\builder\ProjectBuilder;
use poggit\Meta;
use poggit\module\VarPage;
use poggit\release\Release;
use poggit\utils\internet\Mysql;
use poggit\utils\PocketMineApi;
use const poggit\ASSETS_PATH;

class GuestHomePage extends VarPage {
    private $recentPlugins;

    public function getTitle(): string {
        return "Poggit Plugin Platform for PocketMine";
    }

    public function bodyClasses(): array {
        return ["horiz-panes"];
    }

    public function output() {
        ?>
      <div class="guest-maincontent">
          <?php include ASSETS_PATH . "incl/home.guest.php"; ?>
      </div>
      <div class="guesthomepane2">
      <div class="recent-builds-header"><a href="<?= Meta::root() ?>plugins"><h4>Recent Releases</h4></a></div>
      <div class="recent-plugins-sidebar"><?php Release::showRecentPlugins(20); ?></div>
        <?php
    }

    public function getMetaDescription(): string {
        return "Poggit is a GitHub-based plugin release platform, as well as a GitHub application for continuous integration for PocketMine-MP plugins.";
    }
}
