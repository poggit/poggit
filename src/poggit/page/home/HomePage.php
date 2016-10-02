<?php

/*
 * Copyright 2016 poggit
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

namespace poggit\page\home;

use poggit\output\OutputManager;
use poggit\page\Page;
use poggit\Poggit;
use poggit\session\SessionUtils;
use const poggit\EARLY_ACCEPT;

class HomePage extends Page {
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
                <?php $this->headIncludes() ?>
            </head>
            <body>
            <?php $this->bodyHeader() ?>
            <div id="body">
                <h1 class="motto">Concentrate on your code. Leave the dirty work to the machines.</h1>
                <p class="submotto">
                    Automatic development builds. Advanced plugin lint. Synchronized releases with GitHub releases.
                    Vote-based community translations system. Register with GitHub and enable the magic with a few
                    clicks.
                </p>
                <p class="submotto">
                    Why does Poggit exist? Simply to stop this situation from the web comic
                    <a href="https://xkcd.com/1319"><em>xkcd</em></a> from happening.
                    <br>
                    <a href="https://xkcd.com/1319"><img src="https://imgs.xkcd.com/comics/automation.png"></a>
                    <br>
                </p>
            </div>
            </body>
            </html>
            <?php
        } else {
            $login = $session->getLogin();
            ?>
            <html>
            <head>
                <title>Poggit</title>
                <?php $this->headIncludes() ?>
            </head>
            <body>
            <?php $this->bodyHeader() ?>
            <?php $this->includeJs("home") ?>
            <?php $minifier = OutputManager::startMinifyHtml() ?>
            <div id="body">
                <h1>Configure repos</h1>
                <p>As you enable Build or Release for any repos, Poggit will commit a file
                    <code>.poggit/.poggit.yml</code> to your repo if it doesn't already exist.</p>
                <div class="wrapper">
                    <?php
                    $repos = Poggit::ghApiGet("user/repos?per_page=100", $login["access_token"]);
                    $accs = [];
                    foreach($repos as $repo) {
                        if($repo->permissions->push) $accs[$repo->owner->login][] = $repo;
                    }
                    unset($repos); // irrelevant afterwards!
                    $repoIds = [];
                    foreach($accs as $repos) {
                        foreach($repos as $repo) {
                            $repoIds[] = "repoId = " . $repo->id;
                        }
                    }
                    $result = Poggit::queryAndFetch("SELECT repoId, build, rel FROM repos WHERE " . implode(" OR ", $repoIds));
                    $repoData = [];
                    foreach($result as $row) {
                        $repoData[(int) $row["repoId"]] = [
                            "build" => ((int) $row["build"]) > 0,
                            "release" => ((int) $row["rel"]) > 0,
                        ];
                    }
                    //                    foreach($accs as &$repos) {
                    //                        foreach($repos as &$repo) {
                    //                            $repo->updated_at = strtotime($repo->updated_at);
                    //                        }
                    //                    }
                    //                    $start = microtime(true);
                    uksort($accs, function ($a, $b) use ($accs, $login) {
                        if($a === $login["name"]) {
                            return -1;
                        }
                        if($b === $login["name"]) {
                            return 1;
                        }
                        $aRepos = $accs[$a];
                        $bRepos = $accs[$b];
//                        $amax = 0;
//                        $bmax = 0;
//                        foreach($aRepos as $repo) {
//                            $amax = max($amax, $repo->updated_at);
//                        }
//                        foreach($bRepos as $repo) {
//                            $bmax = max($bmax, $repo->updated_at);
//                        }
                        return count($aRepos) <=> count($bRepos);
                    });
                    //                    $end = microtime(true);
                    //                    Poggit::getLog()->d("Sort time: " . ($end - $start));
                    foreach($accs as $owner => $repos) {
                        ?>
                        <div class="toggle" data-name="<?= $owner ?>"
                            <?= $owner === $login["name"] ? "data-opened='true'" : "" ?>>
                            <table class="single-line-table">
                                <tr style="padding: 5px;">
                                    <th>Repo</th>
                                    <th>Poggit build</th>
                                    <th>Poggit release</th>
                                </tr>
                                <?php
                                foreach($repos as $repo) {
                                    $isBuild = isset($repoData[$repo->id]) ? $repoData[$repo->id]["build"] : false;
                                    $isRelease = isset($repoData[$repo->id]) ? $repoData[$repo->id]["release"] : false;
                                    ?>
                                    <tr style="padding-bottom: 10px;">
                                        <td <?= $repo->private ? "data-private='true'" : "" ?> <?= $repo->fork ? "data-fork='true'" : "" ?>>
                                            <?php if($repo->private) { ?>
                                                <img title="Private repos cannot have releases" width="16"
                                                     src="https://maxcdn.icons8.com/Android_L/PNG/24/Very_Basic/lock-24.png">
                                            <?php } ?>
                                            <?php if($repo->fork) { ?>
                                                <img title="This is a fork" width="16"
                                                     src="https://greasyfork.org/forum/uploads/thumbnails/FileUpload/df/f87899bf1034cd4933c374b02eb5ac.png">
                                                <!-- link from first Google Images search result xD -->
                                            <?php } ?>
                                            <a href="<?= $repo->html_url ?>">
                                                <?= htmlspecialchars($repo->name) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <input type="checkbox" class="repo-boolean" data-type="build"
                                                   id="<?= $rand = mt_rand() ?>" data-repo="<?= $repo->id ?>"
                                                <?= $isBuild ? "checked" : "" ?>>
                                            <a href="<?= Poggit::getRootPath() ?>build/<?= $repo->owner->login ?>/<?= $repo->name ?>">Go
                                                to page</a>
                                        </td>
                                        <td>
                                            <input type="checkbox" class="repo-boolean" data-type="release"
                                                   data-repo="<?= $repo->id ?>"
                                                <?= $isRelease ? "checked" : "" ?>
                                                <?php if($repo->private) { ?>
                                                    disabled
                                                    title="Private repos cannot have releases"
                                                <?php } else { ?>
                                                    data-depends="<?= $rand ?>"
                                                <?php } ?>
                                            >
                                            <a href="<?= Poggit::getRootPath() ?>release/<?= $repo->owner->login ?>/<?= $repo->name ?>">Go
                                                to page</a>
                                        </td>
                                    </tr>
                                    <?php
                                }
                                ?>
                            </table>
                        </div>
                        <?php
                    }
                    ?>
                </div>
            </div>
            <?php OutputManager::endMinifyHtml($minifier) ?>
            </body>
            </html>
            <?php
        }
    }
}
