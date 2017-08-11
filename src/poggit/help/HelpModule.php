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

namespace poggit\help;

use poggit\Meta;
use poggit\module\Module;

class HelpModule extends Module {
    public function getName(): string {
        return "help";
    }

    public function output(): void {
        ?>
        <html>
        <head prefix="og: http://ogp.me/ns# fb: http://ogp.me/ns/fb# object: http://ogp.me/ns/object# article: http://ogp.me/ns/article# profile: http://ogp.me/ns/profile#">
            <?php $this->headIncludes("Poggit - Help - Private Resources", "General Help for Poggit PocketMine Plugin CI and distribution") ?>
            <title>Private Resources | Help | Poggit</title>
        </head>
        <body>
        <?php $this->bodyHeader() ?>
        <div id="body">
            <h1 class="topic">Help</h1>
        </div>
        <div class="helpwrapper">
            <h2>What is Poggit?</h2>

            <p>Poggit is a tool for PocketMine-family plugins hosted on GitHub. If you are looking for tried, tested and
                safe plugins to download then open the <a href="<?= Meta::root() . "plugins" ?>">Release</a> page
                and
                browse the recent releases. You can also search by name/category/author/keywords using 'enter' to launch
                and clear the search.<br/><br/>

                If you wish to rate plugins, leave reviews, and access "development" builds of plugins
                that are not yet full "releases",
                please create an account on GitHub and then Login to Poggit using that account.
                <br/><br/>
            </p>
            <h2>Features for Developers</h2>
            <p></p>
            <h3>CI (Continuous Integration): Build .phar files from GitHub source code</h3>

            <p>Poggit will build phars automatically for your project when you push a commit or make a pull request.</p>
            <p>To disable this temporarily you can click 'disable' for the repo in question in your CI admin.</p>
            <p>Login to Poggit and authorize the Poggit application for your user account or your organizations.
                You can find buttons to enable/disable Poggit-CI for any repository (repo) at <a
                        href="<?= Meta::root() . "ci" ?>">CI</a>.
                Poggit will prompt to create the file <code>.poggit.yml</code> (or <code>.poggit/.poggit.yml</code>)
                in that repo to declare the projects to build.</p>
            <p> You do not need to edit the .poggit.yml file for Poggit to add a repo, but you will find an example of
                what Poggit can do at the end of this page</p>

            <h3>Release: Apply for approval to the official approved plugin Release List</h3>

            <p>A project can be released after it has a development build. You can find the release button in the CI
                build page. To improve your chances of a quick review, please make life easy for staff and
                provide as much information as possible, including full documentation of commands, permissions,
                installation, configuration.
                If you make us work hard to check your plugin, you'll have to wait longer.</p>

            <p>After the build is submitted for plugin release/update, it will be added to the "Submitted" queue.
                Basic checks will be conducted by appointed code reviewers to filter out low-quality and malicious
                plugins before the plugin is promoted to "Checked".</p>

            <p>Only code reviewers and moderators can view and download plugins in the "Submitted" queue.
                Reputable members (based on previously released plugins and forum scores) can vote
                to approve or reject plugins that are "Checked".
                If the net score is high enough, it can be promoted to "Voted"
                where the plugin has access to most, but not all, features in Poggit Release,
                and is available for download to everyone.</p>

            <p>Appointed reviewers will review plugins along with reputable members votes through these stages:
                Submitted -> Checked -> Voted -> Approved -> Featured
                until they reach the official "Approved" and "Featured" release lists,
                where they benefit from all the features of Poggit Release.</p>

            <h2>Advanced Features</h2>

            <p>When you enable a plugin in your repo list you are prompted to confirm the contents of the .poggit.yml
                file that Poggit creates for you.
                Usually you can accept the default setting and click "Confirm", but you can also configure the file
                manually. For example:</p>

            <div class="yamlwrapper"><pre class="yamlcode">
branches: master
projects:
  First:
    path: FirstPlugin
    libs:
      - src: libuncommon # name of a project in this repo
        version: 1.0 # semver constraints
        # Shade all programmatic references to virion antigen (main namespace)
        shade: syntax
      - src: librarian/libstrange/libstrange # full path of a project from another repo on Poggit
        # Same version as those in composer
        version: ^1.0.0
        # Blindly replace all virion antigen references
        shade: single
      - vendor: raw
        # This project has a file libs/libodd.phar, i.e. this project has a file FirstPlugin/libs/libodd.phar
        src: libs/libodd.phar
        # Blindly replace all virion antigen references as well as those with the \ escaped
        shade: double
      - vendor: raw
        # This repo has a file, outside the project path of FirstPlugin, at globlibs/libweird.phar.
        # The prefix / means that it is a path relative to repo root, not project path root.
        src: /globlibs/libweird.phar
      - vendor: raw
        src: http://libextraordinary.com/raw.phar # download online without special permissions.
  HelpsFirst:
    path: FirstPluginAux/HelpsFirst
    model: nowhere
  another:
    path: AnotherPlugin
  libuncommon:
    path: UncommonLib
    type: library
    model: virion
</pre>
            </div>
            <p>The <code>branches</code> attribute lets you decide pushes on or pull requests to which branches
                Poggit should respond to. If this is not set to the default Github branch for the repo (in GitHub
                repo
                settings),
                please make sure you add poggit.yml manually to the branch in question.</p>

            <p>You can load multiple projects by adding multiple entries in the <code>projects</code> attribute.
                This is particularly useful if there are multiple plugins on your repo.</p>

            <h4>Limitations</h4>
            <ol>
                <li>Releases cannot be created from private repos. You must publicize your repo if you want to
                    create
                    plugin releases from it.
                </li>
                <li>For convenience of reviewing plugins, and to reduce waiting times, please avoid force-pushing
                    that
                    modifies commit history of existing releases.
                </li>
            </ol>

            <h2>Status</h2>

            <p>The Poggit project is currently under development, hosted on a private server.
                The full source code is available at <a target="_blank" href='https://github.com/poggit/poggit'>https://github.com/poggit/poggit</a>.
                As of Jan 25 2017, Poggit-CI and Release are functional, but other parts of the website are
                incomplete.
            </p>
        </div>
        <?php $this->bodyFooter() ?>
        </body>
        </html>
        <?php
    }
}
