<?php

use poggit\ci\builder\ProjectBuilder;
use poggit\home\SimpleStats;
use poggit\Meta;
use poggit\release\Release;
use poggit\utils\internet\Mysql;
use poggit\utils\PocketMineApi;

$simpleStats = new SimpleStats();
?>
<h1 class="motto">High Quality PocketMine Plugins</h1>
<h2 class="submotto">
  Download reviewed plugins with simple URLs from <a href="<?= Meta::root() ?>plugins">Poggit Release</a>
</h2>
<p>
  Plugins released on Poggit are reviewed by Poggit staff and members of the community. You can filter the plugins
  your server can use, find the latest or best-received plugins, search the plugins you need and download the plugin
  onto your server directly.
</p>
<p>
  Currently <?= $simpleStats->releases ?> plugins have been released on Poggit Release,
  of which <?= $simpleStats->compatibleReleases ?> can run on API <?= PocketMineApi::LATEST_COMPAT ?>
</p>
<p>
  In addition to the web interface, the <a href="<?= Meta::root() ?>p/Sheep">Sheep plugin</a> by
  <a href="https://github.com/KnownUnown" target="_blank">KnownUnown</a> allows you to manage your plugins from Poggit
  right from the server console.
</p>
<p>
  You may also download plugins from the command line using tools like wget or curl: <code>wget <?=
        Meta::getSecret("meta.extPath") ?>get/Sheep</code> See the <a href="<?= Meta::root() ?>help.api">API
    documentation</a> for more details.
</p>
<h2 class="submotto">Vote to approve, reject or review plugins</h2>
<p>
  Poggit has a community of <?= $simpleStats->users ?> registered users and up to <?= $simpleStats->visitingIps ?>
  unregistered users. Many parts of Poggit are supported by this community:
</p>
<p>
  Logged-in users can access plugins that have only been tentatively approved ("Checked"). Poggit staff have
  not yet reviewed them carefully, but checked plugins will probably not intentionally harm your server, install viruses
  etc.
  You may try these plugins at a small risk, and if you think a checked plugin is good enough to be listed
  you can vote to approve it. On the other hand, if you find it quite useless or so buggy that it doesn't deserve to
  be approved on the plugin list, you can vote to reject it. With enough votes, the plugin will become rejected or
  approved.
</p>
<p>
  Logged-in users can also give plugins a score and leave review comments to tell other users if the
  plugin is good, useful, laggy, inconvenient etc.
</p>
<h2 class="motto">Tools for Developers</h2>
<h3 class="submotto">Build phar files automatically from GitHub source code. Lint tailored for PocketMine plugins.<br/>
  Register with GitHub in a few seconds to enable the magic.</h3>
<p>
  If you set up your plugins GitHub repo with <a href="<?= Meta::root() ?>ci">Poggit-CI</a>, Poggit-CI will create a
  .phar file from your code every time you push commits to the designated branches. This will allow your users to
  update to the latest unreleased snapshots of your plugin, and you don't have to build the plugin and upload it
  yourself.
</p>
<p>
  When you receive pull requests, Poggit also creates PR builds, so you can test the pull request by downloading a
  build from Poggit CI directly. PR builds may be dangerous to use!
</p>
<h3 class="submotto">Virions &mdash; Libraries for PocketMine plugins</h3>
<p>
  Some developers write libraries specifically for PocketMine plugins, which you can include automatically within your
  own builds using Poggit. See the
  <a href="<?= Meta::root() ?>virion">Virion Documentation</a> for details. You may find a list of virions
  <a href="<?= Meta::root() ?>v">here</a>.
</p>
<h3 class="submotto">Lint for PocketMine Plugins</h3>
<p>
  Poggit will check your plugin for common problems after creating a build. You can consult the lint result
  on the Poggit-CI page. The lint result will also be reported as GitHub status checks, which will do
  <a target="_blank" href="<?= Meta::root() ?>ghhst">many cool things</a>.
</p>
<p class="remark">
  Poggit cannot test the builds for you, but there is a script that you can put into your
  <a href="https://docs.travis-ci.com/user/getting-started/">Travis-CI</a> build which will download builds from
  Poggit for testing. Refer to
  <a href="https://github.com/LegendOfMCPE/WorldEditArt/blob/b566a0f/.travis.yml" target="_blank">this example in
    WorldEditArt</a>.</p>
<p><h2 class="motto">Concentrate on your code.<br/>Leave the dirty work to the machines.</h2></p>
<div class="brief-info">
  <h3>Boring stats</h3>
</div>
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
