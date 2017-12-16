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

use poggit\Meta;
use poggit\module\VarPage;
use poggit\release\Release;
use poggit\utils\PocketMineApi;

use const poggit\ASSETS_PATH;

class GuestHomePage extends VarPage {
    public function getTitle(): string {
        return "Poggit Plugin Platform for PocketMine";
    }

    public function bodyClasses(): array {
        return ["horiz-panes"];
    }

    public function output() {
        $simpleStats = new SimpleStats();
        ?>
      <div class="guest-maincontent">
        <h1 class="motto">High Quality PocketMine Plugins</h1>
        <h2 class="submotto">
          Download reviewed plugins with simple URLs from <a href="<?= Meta::root() ?>plugins">Poggit Release</a>
        </h2>
        <p>
          Poggit is the official plugin repository for PocketMine Minecraft Servers running
          PMMP PocketMine. For more information on setting up a PocketMine Server please see the
          <a target="_blank" href="http://pmmp.readthedocs.io/en/rtfd/installation.html">documentation</a>
          , or browse the <a target="_blank" href="http://forums.pmmp.io">PMMP forums.</a>  Server owners can
        download plugins, subscribe to projects, or vote for and review plugins. Developers can log in with
        a GitHub account to build plugins directly from Github, and submit them for release on Poggit.</p>
        <p>
          Plugins released on Poggit are reviewed by staff and members of the community. You can filter plugins
          by API version to list those that are compatible with your server, browse the latest or most popular plugins,
          search for the plugins you need, and download the plugin .phar files to place in your servers 'plugins' folder.
        </p>
          <?php include ASSETS_PATH . "incl/home.common.php"; ?>
        <div class="brief-info" id="home-stats">
          <h3>Boring stats</h3>
          <p>Users registered: <?= $simpleStats->users ?></p>
          <p>Repos integrated: <?= $simpleStats->repos ?></p>
          <p>Plugin Projects created: <?= $simpleStats->pluginProjects ?></p>
          <p>Plugin Builds created: <?= $simpleStats->pluginBuilds ?></p>
          <p>Virion Projects created: <?= $simpleStats->virionProjects ?></p>
          <p>Virion Builds created: <?= $simpleStats->virionBuilds ?></p>
          <p>Released plugins (at least one version <em>Voted</em> or above): <?= $simpleStats->releases ?></p>
          <p>Compatible released plugins (at least one version <em>Voted</em> or above,
            compatible with <?= PocketMineApi::LATEST_COMPAT ?>): <?= $simpleStats->compatibleReleases ?></p>
          <p>Total released plugin downloads: <?= $simpleStats->pluginDownloads ?></p>
          <p>Number of IP addresses visiting Poggit: <?= $simpleStats->visitingIps ?></p>
        </div>
      </div>
      <div class="guesthomepane2">
      <div class="recent-builds-header"><a href="<?= Meta::root() ?>plugins"><h4>Top Releases</h4></a></div>
        <div class="recent-plugins-sidebar"><?php Release::showTopPlugins(10); ?><a href="<?= Meta::root() ?>plugins"><div class="action">See All...</div></a></div>
      </div>
        <?php
    }

    public function getMetaDescription(): string {
        return "Poggit is a GitHub-based plugin release platform, as well as a GitHub application for continuous integration for PocketMine-MP plugins.";
    }
}
