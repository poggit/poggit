<?php

use poggit\ci\builder\ProjectBuilder;
use poggit\Meta;
use poggit\release\Release;
use poggit\utils\internet\Mysql;
use poggit\utils\PocketMineApi;

$simpleStats = Mysql::query("SELECT
    (SELECT COUNT(*) FROM users) users,
    (SELECT COUNT(*) FROM projects WHERE type = ?) pluginProjects,
    (SELECT COUNT(*) FROM projects WHERE type = ?) virionProjects,
    (SELECT COUNT(DISTINCT projectId) FROM releases WHERE state >= ?) releases,
    (SELECT COUNT(DISTINCT releases.projectId) FROM releases
        INNER JOIN release_spoons ON releases.releaseId = release_spoons.releaseId
        INNER JOIN known_spoons since ON release_spoons.since = since.name
        INNER JOIN known_spoons till ON release_spoons.till = till.name
        WHERE releases.state >= ? AND 
            (SELECT id FROM known_spoons WHERE name = ?) BETWEEN since.id AND till.id) compatibleReleases,
    (SELECT COUNT(DISTINCT ip) FROM rsr_dl_ips) dlIps",
    "iiiis", ProjectBuilder::PROJECT_TYPE_PLUGIN, ProjectBuilder::PROJECT_TYPE_LIBRARY,
    Release::STATE_CHECKED, Release::STATE_CHECKED,
    PocketMineApi::LATEST_COMPAT)[0];
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
    Currently, <?= $simpleStats["releases"] ?> plugins have been released on Poggit Release,
    where <?= $simpleStats["compatibleReleases"] ?> of them can run on API <?= PocketMineApi::LATEST_COMPAT ?>
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
    Poggit has a community of <?= $simpleStats["users"] ?> registered users and up to <?= $simpleStats["dlIps"] ?>
    unregistered users to date. Many parts of Poggit are supported by this community:
</p>
<p>
    Logged-in users can access plugins that have only been tentatively approved ("Checked") &mdash; Poggit staff have
    not carefully reviewed them yet, but they most likely won't intentionally harm your server, install viruses, etc.
    You may try using these plugins at a small risk, and if you think a "Checked" plugin is good enough to be listed,
    you can vote to approve it. On the other hand, if you find it quite useless or very buggy that it doesn't deserve to
    be approved on the plugin list, you can vote to reject it. With enough votes, the plugin will become rejected or
    approved.
    <br/>
    Logged-in users can also give all plugins a score and leave some review comments to tell other users if the
    plugin is good, useful, laggy, inconvenient, etc. Currently
</p>
<h2 class="motto">Tools for Developers</h2>
<h3 class="submotto">Automatically create development builds</h3>
<p>
    If you setup your plugin's GitHub repo with <a href="<?= Meta::root() ?>ci">Poggit-CI</a>, Poggit-CI will create a
    .phar file from your code every time you push commits to the designated branches. This will allow your users to
    update to the latest unreleased snapshots of your plugin, and you don't have to build the plugin and upload it
    yourself.
</p>
<p>
    When you receive pull requests, Poggit also creates PR builds, so you can test the pull request by downloading a
    build from Poggit CI directly. (PR builds may be dangerous to use!)
</p>
<h3 class="submotto">Virions &mdash; Libraries for PocketMine plugins</h3>
<p>
    Some developers write libraries specific for PocketMine plugins, which you may include them with Poggit. See the
    <a href="<?= Meta::root() ?>virion">Virion Documentation</a> for details. You may find a list of virions
    <a href="<?= Meta::root() ?>v">here</a>.
</p>
<h3 class="submotto">Lint for PocketMine Plugins</h3>
<p>
    Poggit will check if your plugin has got some common problems after creating a build. You can check the lint result
    on the Poggit-CI page. The lint result will also be reported as GitHub status checks, which will do
    <a target="_blank" href="<?= Meta::root() ?>ghhst">many cool things</a>.
</p>
<p class="remark">
    Poggit cannot test the builds for you, but there is a script that you can put into your
    <a href="https://docs.travis-ci.com/user/getting-started/">Travis-CI</a> build, which will download builds from
    Poggit for testing. Refer to
    <a href="https://github.com/LegendOfMCPE/WorldEditArt/blob/b566a0f/.travis.yml" target="_blank">this example in
        WorldEditArt</a>.</p>

<h2 class="motto">Concentrate on your code.<br/>Leave the dirty work to the machines.</h2>
<h3 class="submotto">Download plugins easily. Automatic development builds. Lint tailored for PocketMine plugins.<br/>
    Register with GitHub in a few seconds to enable the magic.</h3>
