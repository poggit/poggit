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

use poggit\module\VarPage;
use poggit\Meta;
use poggit\release\PluginRelease;

class GuestHomePage extends VarPage {
    private $recentPlugins;

    public function __construct() {
        $this->recentPlugins = PluginRelease::getRecentPlugins(20, true);
    }

    public function getTitle(): string {
        return "Poggit Plugin Platform for PocketMine: from Code to Release";
    }

    public function bodyClasses(): array {
        return ["horiz-panes"];
    }

    public function output() {
        ?>
        <div class="guesthomepane1">
            <h1 class="motto">High Quality PocketMine Plugins</h1>
            <h2 class="submotto">Download reviewed plugins with simple URLs from Poggit "Release"</h2>
            <p>When developers submit plugins to Poggit they are reviewed and tested before being released to the
                public.
                Poggit Plugins are therefore considered safe to use, and quality is generally promising. Log in with a
                GitHub account
                to see more plugins, leave reviews, and start building your own plugins. If you haven't done so already,
                make sure
                you visit the <a target='_blank' href="http://forums.pmmp.io">official PMMP forums</a> for PocketMine
                help, advice, tutorials and more.</p>
            <p>The plugin index is categorized, and each released plugin is versioned. Use the search box to list
                Poggit plugins by category, author & keywords.</p>
            <p><span onclick='window.location = <?= json_encode(Meta::root() . "plugins") ?>;' class="action">Display
                    latest plugins</span></p>
            <h2 class="motto">Build Projects Automatically with Poggit</h2>
            <h3 class="submotto">Create builds the moment you push to GitHub.</h3>
            <p>Poggit CI will set up webhooks in your repos to link to Poggit. When you push a commit to your repo,
                Poggit will create a development build. When you receive pull requests, Poggit also creates PR builds,
                so you can test the pull request by downloading a build from Poggit CI directly.</p>
            <p>Different plugin frameworks are supported. Currently, the normal one with a <code
                        class="code">plugin.yml</code>, and the NOWHERE framework, can be used.</p>
            <p>An online language manager can also be enabled. After you push some language files to your repo, there
                will be a webpage for online translator, and other people can help you translate your plugin to other
                languages. Then the poglang library will be compiled with your plugin, along with some language files
                contributed by the community.</p>
            <p><span onclick='login(<?= json_encode(Meta::getSecret("meta.extPath") . "ci") ?>, true);'
                     class="action">Register with GitHub to setup projects</span></p>
            <h2 class="motto">Lint for PocketMine Plugins</h2>
            <h3 class="submotto">Check pull requests before you merge them.</h3>
            <p>After Poggit CI creates a build for your project, it will also execute lint on it. Basically, lint is
                something that checks if your code has problems. See <a
                        href="<?= Meta::root() ?>help.lint">Poggit Help: Lint</a> for everything the lint
                checks.
            </p>
            <p>You can check out the lint result on the Poggit Build page. The lint result will also be uploaded to
                GitHub, in the form of status checks, which will do
                <a target="_blank" href="<?= Meta::root() ?>ghhst">many cool things</a>.</p>
            <p class="remark">Note: Poggit cannot test the builds for you, but there is a script that you can put into
                your <a href="https://docs.travis-ci.com/user/getting-started/">Travis-CI</a> build, which will wait for
                and then download builds from Poggit for testing.</p>

            <p>
            <h2 class="motto">Concentrate on your code.<br/> Leave the dirty work to the machines.</h2>
            <h3 class="submotto">Download plugins easily. Automatic development builds. Lint tailored for
                PocketMine plugins.<br/>
                Register with GitHub in a few seconds to enable the magic.</h3></p>
            <p class="submotto">Why does Poggit exist? Simply to stop a situation from the web comic
                <a target="_blank" href="https://xkcd.com/1319"><em>xkcd</em></a> from happening.<br/>
        </div>
        <div class="guesthomepane2">
        <div class="recentbuildsheader"><a href="<?= Meta::root() ?>plugins"><h4>Recent Releases</h4></a></div>
        <div class="recent-plugins-sidebar">
            <?php
            if(isset($this->recentPlugins)) {
                foreach($this->recentPlugins as $plugin) { ?>
                    <div class="plugin-index"><?php
                        PluginRelease::pluginPanel($plugin); ?>
                    </div>
                <?php }
            } ?>
        </div>
        <?php
    }

    public function getMetaDescription(): string {
        return "Poggit is a GitHub-based plugin release platform, as well as a GitHub application for continuous integration for PocketMine-MP plugins.";
    }
}
