<?php

use poggit\Meta;
use poggit\utils\PocketMineApi;

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
<h2 class="motto">Vote and Review Plugins</h2>
<p>
  Logged-in users can access plugins that are "Checked" but not yet publicly released;
  Poggit staff have checked them for malicious code, but have
  not yet reviewed them carefully. Checked plugins will probably not intentionally harm your server or install viruses
  etc. but there is a small risk nonetheless.
  If you think a checked plugin is good enough to be listed in "Releases"
  you can vote to approve it. On the other hand, if you find it useless or too buggy
  you can vote to reject it. With enough votes the plugin will become "Rejected" or
  "Voted"/"Approved".
</p>
<p>
  Logged-in users can also leave review comments with scores to let other users know if the
  plugin is good, useful, laggy, inconvenient etc.
</p>
<h2 class="motto">Tools for Developers</h2>
<h3 class="submotto">Build phar files automatically from GitHub source code.</h3>
<p>
  Once you have set up your plugins GitHub repo with <a href="<?= Meta::root() ?>ci">Poggit-CI</a>, Poggit-CI will create a
  .phar file from your code every time you push commits to the designated branches. This allows your users to
  update to the latest unreleased snapshots of your plugin without you having to build the plugin and upload it
  yourself.
</p>
<p>
  When you receive pull requests Poggit also creates PR builds, so you can test the pull request by downloading a
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
