<?php

use poggit\home\SimpleStats;
use poggit\Meta;
use poggit\utils\PocketMineApi;

/** @var SimpleStats $simpleStats */
?>
<p>
  Currently <?= $simpleStats->releases ?> plugins have been released on Poggit Release,
  of which <?= $simpleStats->compatibleReleases ?> can run on API <?= PocketMineApi::$LATEST_COMPAT ?>
</p>
<div class="alert alert-success" role="alert">
  The <a href="<?= Meta::root() ?>p/Sheep">Sheep plugin</a> by
  <a href="https://github.com/KnownUnown" target="_blank">KnownUnown</a>
  works with Poggit to help you to manage your plugins
  right from the server console.
</div>
<p>
  You may also download plugins from the command line using tools like wget or curl: <code>wget <?=
        Meta::getSecret("meta.extPath") ?>get/Sheep</code> See the <a href="<?= Meta::root() ?>help.api">API
    documentation</a> for more details.
</p>
<h2 class="motto">Review Plugins</h2>
<p>
  You can vote on a plugin and give comments to let others know if the plugin is good.
  You can also give suggestions there, but remember not to use reviews as the bug tracker!
</p>
<h2 class="motto">Tools for Developers</h2>
<h3 class="submotto">Build phar files automatically from GitHub source code.</h3>
<p>
  Once you have set up your plugins GitHub repo with <a href="<?= Meta::root() ?>ci">Poggit-CI</a>, Poggit-CI will
  create a
  .phar file from your code every time you push commits to the designated branches. This allows your users to
  update to the latest unreleased snapshots of your plugin without you having to build the plugin and upload it
  yourself.
</p>
<p>
  When you receive pull requests Poggit also creates Pull Request builds, so you can test the pull request by downloading a
  build from Poggit CI directly. Pull Request builds may be dangerous to use!
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
