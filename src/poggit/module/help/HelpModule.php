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

namespace poggit\module\help;

use poggit\module\Module;

class HelpModule extends Module {
    public function getName(): string {
        return "help";
    }

    public function output() {
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
                safe
                plugins to download then open the <a href="/poggit">Homepage</a> and
                browse the recent releases. You can also search by name/category/author,
                etc, read news articles, and more.<br/><br/>

                If you wish to rate plugins, leave reviews, and access "development" builds of plugins
                that are not yet full "releases",
                then please create an account on GitHub and then Login to Poggit using that account.
                <br/><br/>
            </p>
            <h2>Features for Developers</h2>
            <p></p>
            <h3>CI (Continuous Integration): Build .phar files from GitHub source code</h3>

            <p>Poggit will build phars automatically for your project when you push a commit or make a pull request.</p>

            <p>Login to Poggit and authorize the Poggit application for your user account or your organizations.
                You can find buttons to enable Poggit-CI for any repository (repo) at <code>/ci</code>.
                Poggit will prompt to create the file <code>.poggit.yml</code> (or <code>.poggit/.poggit.yml</code>)
                in that repo to declare the projects to build.</p>
            <p> You do not need to edit the poggit.yml file for Poggit to add a repo, but you will find an example of
                what Poggit can do at the end of this page</p>

            <h3>Release: Apply for approval to the official approved plugin Release List</h3>

            <p>A project can be released after it has a development build. You can find the release button in the CI
                build page.</p>

            <p>After the build is submitted for plugin release/update, it will be added to the "Unapproved Queue".
                Basic checks will be conducted by appointed reviewers to filter out low-quality and malicious
                plugins before the plugin is moved to the "Deep Queue", a reference to the "Deep Web".</p>

            <p>Only registered members can view and download plugins in the deep queue.
                Reputable members (based on previously released plugins and forum scores) can vote
                to approve or reject plugins in the deep queue.
                If the net score is high enough, it can be moved to the unofficial queue
                where the plugin has access to most, but not all, features in Poggit Release,
                and is available for download to both Guests and Members.</p>

            <p>Appointed reviewers will review plugins in the "Unofficial" and "Deep" queues to move them to the
                official release list,
                where they benefit from all the features of Poggit Release.</p>

            <h2>Advanced Features</h2>

            <p>When you enable a plugin in your repo list you are prompted to confirm the contents of the .poggit.yml
                file that Poggit creates for you.
                Usually you can accept the default setting and click "Confirm", but you can also configure the file
                manually. For example:

            <div class="yamlwrapper"><pre class="yamlcode">
branches: master
projects:
  First:
    path: FirstPlugin
    libs:
      - local: libuncommon <span class="code"># name of a project in this repo</span>
      - external: librarian/libstrange/libstrange <span class="code"># full path of a project from another repo on Poggit</span>
      - raw-virion: libs/libodd.phar <span class="code"># this repo has a file libs/libodd.phar</span>
      - raw-virion: http://libextraordinary.com/raw.phar
  another:
    path: AnotherPlugin
    model: virion
  libuncommon:
    path: UncommonLib
    type: library
    model: virion
</pre>
            </div>

            <p>The <code>branches</code> attribute lets you decide pushes on or pull requests to which branches
                Poggit should respond to.</p>

            <p>You can load multiple projects by adding multiple entries in the <code>projects</code> attribute.
                This is particularly useful if there are multiple plugins on your repo.</p>

            <h4>Limitations</h4>
            <ol>
                <li>Releases cannot be created from private repos. You must publicize your repo if you want to create
                    plugin releases from it.
                </li>
                <li>For convenience of reviewing plugins, and to reduce waiting times, please avoid force-pushing that
                    modifies commit history of existing releases.
                </li>
            </ol>

            <h2>Status</h2>

            <p>The Poggit project is currently under development, hosted on a private server.
                The full source code is available at <a target="_blank" href='https://github.com/poggit/poggit'>https://github.com/poggit/poggit</a>.
                As of Nov 25 2016, Poggit-CI is considered functional, but other parts of the website are incomplete.
            </p>

        </div>
        <?php $this->bodyFooter() ?>
        </body>
        </html>
        <?php
    }
}
