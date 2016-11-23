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

namespace poggit\module\help;

use poggit\module\Module;
use poggit\output\OutputManager;

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
            <div class="mainwrapper">
<div class="helpwrapper">
<h1>What is Poggit?</h1>

<p>Poggit is a tool for PocketMine-family plugins hosted on GitHub. If you are looking for tried, tested and safe
    plugins to download then open the <a href="/poggit">Homepage</a> and browse the recent releases. You can also search by name/category/author,
    etc, read news articles, and more.<br/><br/>
    
    If you wish to rate plugins, leave reviews, and access "development" builds of plugins that are not yet official "releases",
    then please create an account on GitHub and then Login to Poggit using that account.
    <br/><br/>
</p>
<h2>Features</h2>

<h3>CI : Build .phar files from GitHub source code</h3>

<p>Poggit will build phars for your project when you push a commit or make a pull request.</p>

<p>Login on the Poggit website and authorize the Poggit application for your user account or your organizations. You can find buttons to enable Poggit-CI for particular repos at <code>/ci</code>. Poggit will prompt to create the file <code>.poggit/.poggit.yml</code> (or just <code>.poggit.yml</code>) in your repo to declare the projects to build in this repo. This example shows what Poggit can do:</p>

<div class="yamlwrapper"><pre class="yamlcode">
branches: master
projects:
  First:
    path: FirstPlugin
    libs:
      - local: libuncommon <span style="color: #888888"># name of a project in this repo</span>
      - external: librarian/libstrange/libstrange <span style="color: #888888"># full path of a project from another repo on Poggit</span>
      - raw-virion: libs/libodd.phar <span style="color: #888888"># this repo has a file libs/libodd.phar</span>
      - raw-virion: http://libextraordinary.com/raw.phar
    aux: [<span style="color: #996633">HelpsFirst</span>]
  HelpsFirst: 
    path: FirstPluginAux/HelpsFirst
    model: nowhere
  another:
    path: AnotherPlugin
  libuncommon:
    path: UncommonLib
    type: library
    model: virion
</pre></div>

<p>The <code>branches</code> attribute lets you decide pushes on or pull requests to which branches Poggit should respond to.</p>

<p>You can load multiple projects by adding multiple entries in the <code>projects</code> attribute. This is particularly useful if there are multiple plugins on your repo.</p>





<h3>Release (Plugin List)</h3>

<p>A project can be released after it has a development build. You can find the release button in the CI build page.</p>

<p>After the build is submitted for plugin release/update, it will be added to the "Unapproved" queue. Very basic check will be conducted by appointed reviewers to filter away very low-quality plugins and some malicious plugins, before the plugin is moved to the "Deep" queue (a reference to the "Deep Web").</p>

<p>Only registered members can view and download plugins in the "Deep" queue. Reputable members (based on their previously released plugins as well as probably some scores from their Forums account) can vote to approve or reject plugins in the "Deep" queue based on testing. If the net score (based on approving/rejecting members' own reputation) is high enough, it can be moved to the "Unofficial" queue, where the plugin gains access to most features in Poggit Release, and is visible to everyone.</p>

<p>Appointed reviewers will review plugins in the "Unofficial" and "Deep" queues to move them to the official list, where it can gain all features of Poggit Release.</p>

<h4>Limitations</h4>

<ol>
<li>Releases cannot be created from private repos. You must publicize your repo if you want to create plugin releases from it.</li>
<li>For convenience of reviewing plugins, avoid force-pushing that modifies commit history of existing releases.</li>
</ol>





<h2>Status</h2>

<p>The Poggit project is currently under development, hosted on a private server. As of Oct 25 2016, Poggit-CI is considered functional for stricter testing, but other parts of the website are yet far from completion.</p>

<h2>Can I host it myself?</h2>

<p>Yes, you can, although discouraged.</p>

<p>Poggit manages a website that allows users to download plugins, to find plugins from. Therefore, if everyone creates their own Poggit website, Poggit will lose its meaning. For the sake of the community, unless you have improved your version of Poggit so much that the original version is no longer worth existing, please don't host a public Poggit yourself.</p>

<p>However, for various reasons, mainly that I am a stubborn Free Software supporter, you can still host a Poggit instance yourself. This project is licensed under the Apache license, Version 2.0. You can obtain a full copy of this license at the <a href="http://github.com/poggit/poggit/blob/master/LICENSE">LICENSE</a> file.</p>

<p>Nevertheless, Poggit is open-sourced for developers, not businesses. It is here for developers to improve it, or to learn from it, <em>"to build software better, <strong>together</strong>"</em>. You are welcome if you want to host Poggit yourself for security reasons, or for testing. But if you are hosting Poggit for profit reasons, <strong>I politely ask you not to do that</strong>.</p>

<h2>Installation</h2>

<p><strong>Please</strong> read  before installing Poggit.</p>

<p>Then, refer to <a href="http://github.com/poggit/poggit/blob/master/INSTALL.md">INSTALL.md</a> for instructions to install Poggit.</p>

<h2>Why not Composer?</h2>

<p>Simple and real answer: I don't like composer.</p>

<p><a href="https://github.com/Falkirks">@Falkirks</a> has created a modified version of Composer, called <a href="https://github.com/Falkirks/Miner">Miner</a>, for PocketMine plugins to use composer, but it is not adapted to PocketMine plugins enough. It is planned that Poggit will add features specific to PocketMine plugins that can't be used with Composer, as well as convenient deployment of PocketMine plugins.</p>
  </div>

</div>
        </body>
        </html>
        <?php
    }
}
