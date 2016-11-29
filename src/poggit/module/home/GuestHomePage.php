<?php

/*
 * Poggit
 *
 * Copyright (C) 2016 Poggit
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

namespace poggit\module\home;

use poggit\module\VarPage;
use poggit\Poggit;

class GuestHomePage extends VarPage {
    private $recentBuilds;

    public function __construct() {
        foreach(Poggit::queryAndFetch("SELECT b.buildId, b.internal, b.class, UNIX_TIMESTAMP(b.created) AS created, 
            r.owner, r.name AS repoName, p.name AS projectName
            FROM builds b INNER JOIN projects p ON b.projectId = p.projectId INNER JOIN repos r ON p.repoId = r.repoId
            WHERE class = ? AND private = 0 AND r.build > 0 ORDER BY created DESC LIMIT 10", "i", Poggit::BUILD_CLASS_DEV) as $row) {
            $row = (object) $row;
            $buildId = $row->buildId = (int) $row->buildId;
            $row->internal = (int) $row->internal;
            $row->class = (int) $row->class;
            $row->created = (int) $row->created;
            $this->recentBuilds[$buildId] = $row;
        }
    }

    public function getTitle(): string {
        return "Poggit - Concentrate on your code. Leave the dirty work to the machines.";
    }

    public function bodyClasses(): array {
        return ["horiz-panes"];
    }

    public function output() {
        ?>
        <div class="guesthomepane1">
            <h1 class="motto">Welcome To Poggit<br/></h1>
            <h2 class="submotto">PocketMine-Family Plugin Distribution & Building</h2>
            <p>Poggit exists to make life easier for both plugin developers and end-users. If you need reliable,
            safe and verified plugins for your server then you have come to right place. If you are a developer
            looking for a simple workflow to distribute and update your plugin releases, complete with
            PocketMine specific code analysis to help you improve your code, Poggit is here to help.
            <h1 class="motto">Plugin Downloads</h1>
            <h2 class="submotto">Download reviewed plugins with simple URLs.</h2>
            <p>When plugins are submitted by developers to Poggit they are reviewed by Code Reviewers and Test
                Reviewers before being released to the public. Plugins that you download from Poggit are
                therefore considered to be safe to use, and quality is generally promising.</p>
            <p>Users who log in to Poggit with a GitHub account also have access to "development" builds that are not yet
                fully approved "releases". Members can also rate/review plugins, and have access to Poggit's Continuous Integration (CI)
            tools that allows developers to build and submit plugins for release.</p>
            <p>The plugin index is categorized, and each released plugin is versioned. You can also filter them by type
                of <span title="A spoon is a variant of PocketMine-MP. Examples include pmmp, Genisys, ClearSky, etc."
                         class="hover-title">spoon</span> that you use, number of downloads, ratings, etc.</p>
            <p><span onclick='window.location = <?= json_encode(Poggit::getRootPath() . "ci") ?>;' class="action">Look
                    for latest plugins</span></p>
            <hr/>
            <h1 class="motto">Build Your Projects</h1>
            <h2 class="submotto">Create builds the moment you push to GitHub.</h2>
            <p>Poggit CI will set up webhooks in your repos to link to Poggit. When you push a commit to your repo,
                Poggit will create a development build. When you receive pull requests, Poggit also creates PR builds,
                so you can test the pull request by downloading a build from Poggit CI directly.</p>
            <p>Different plugin frameworks are supported. Currently, the normal one with a <code
                        class="code">plugin.yml</code>, and the NOWHERE framework, can be used.</p>
            <p>An online language manager can also be enabled. After you push some language files to your repo, there
                will be a webpage for online translator, and other people can help you translate your plugin to other
                languages. Then the poglang library will be compiled with your plugin, along with some language files
                contributed by the community.</p>
            <p><span onclick='login(<?= json_encode(Poggit::getSecret("meta.extPath") . "ci.cfg") ?>, true);'
                     class="action">Register with GitHub to setup projects</span></p>
            <hr/>
            <h1 class="motto">Lint for PocketMine Plugins</h1>
            <h2 class="submotto">Checks pull request before you can merge them.</h2>
            <p>After Poggit CI creates a build for your project, it will also execute lint on it. Basically, lint is
                something that checks if your code is having problems. See <a
                        href="<?= Poggit::getRootPath() ?>help.lint">Poggit Help: Lint</a> for what the lint checks.
            </p>
            <p>You can check out the lint result on the Poggit Build page. The lint result will also be uploaded to
                GitHub, in the form of status checks, which will do
                <a target="_blank" href="<?= Poggit::getRootPath() ?>ghhst">many cool things</a>.</p>
            <p class="remark">Note: Poggit cannot test the builds for you, but there is a script that you can put into
                your <a href="https://docs.travis-ci.com/user/getting-started/">Travis-CI</a> build, which will wait for
                and then download builds from Poggit for testing.</p>
        </div>
        <div class="guesthomepane2">
            <h4>Recent builds</h4>
            <?php
            foreach($this->recentBuilds as $build) {
                $permLink = dechex((int) $build->buildId);
                ?>
                <div class="brief-info">
                    <p class="recentbuildbox">
                        <a href="<?= Poggit::getRootPath() ?>ci/<?= $build->owner ?>/<?= $build->repoName ?>">
                            <?= htmlspecialchars($build->projectName) ?></a> &amp;<?= $permLink ?><br/>
                        <span class="remark">(<?= $build->owner ?>/<?= $build->repoName ?>)<br/>
                            <?= Poggit::$BUILD_CLASS_HUMAN[$build->class] ?> Build #<?= $build->internal ?><br/>
                        Created <span class="time-elapse" data-timestamp="<?= $build->created ?>"></span> ago</span>
                    </p>
                </div>
            <?php } ?>
        </div>
        <?php
    }

    public function getMetaDescription(): string {
        return "Poggit is a GitHub-based plugin release platform, as well as a GitHub application for continuous integration for PocketMine-MP plugins.";
    }
}
