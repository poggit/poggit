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

use poggit\module\Module;
use poggit\output\OutputManager;
use poggit\session\SessionUtils;

class HomeModule extends Module {
    public function getName() : string {
        return "home";
    }

    public function output() {
        $session = SessionUtils::getInstance();
        if(!$session->isLoggedIn()) {
            ?>
            <html>
            <head>
                <title>Poggit</title>
                <?php $this->headIncludes("Poggit", "Concentrate on your code. Leave the dirty work to the machines.") ?>
            </head>
            <body>
            <?php $this->bodyHeader() ?>
            <div id="body">
                <h1 class="motto">Concentrate on your code. Leave the dirty work to the machines.</h1>
                <p class="submotto">
                    Automatic development builds. Advanced plugin list. Synchronized releases with GitHub releases.
                    Vote-based community translations system. Register with GitHub and enable the magic with a few
                    clicks.
                </p>
                <p class="submotto">
                    Why does Poggit exist? Simply to stop this situation from the web comic
                    <a href="https://xkcd.com/1319"><em>xkcd</em></a> from happening.
                    <br>
                    <a href="https://xkcd.com/1319"><img src="https://imgs.xkcd.com/comics/automation.png"></a>
                    <br>
                    Poggit-CI will take take your Github Plugin Repositories and make releases for you! Fully automated  releasing with flexible configuration!
                </p>
            </div>
            </body>
            </html>
            <?php
        } else {
            ?>
            <html>
            <head>
                <title>Poggit</title>
                <?php $this->headIncludes("I thought OGP does not use cookies?", "You are logged in...") ?>
            </head>
            <body>
            <?php $this->bodyHeader() ?>
            <?php $this->includeJs("home") ?>
            <?php $minifier = OutputManager::startMinifyHtml() ?>
            <div id="body">
                <h1>Latest plugins</h1>
                <p>Check out the Plugin List for more Plugins!</p>
                <!-- TODO -->

                <h1>Configure repos</h1>
                <p>As you enable Build or Release for any repos, Poggit will commit a file
                    <code>.poggit/.poggit.yml</code> to your repo if it doesn't already exist.</p>
                <div class="wrapper" id="repo-config">
                    Loading your repositories&#8230; (This may take a while if you have many repos)
                </div>
            </div>
            <?php OutputManager::endMinifyHtml($minifier) ?>
            </body>
            </html>
            <?php
        }
    }
}
