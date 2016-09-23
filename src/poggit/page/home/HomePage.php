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

use poggit\page\Page;
use poggit\session\SessionUtils;
use const poggit\EARLY_ACCEPT;
use function poggit\curlGet;
use function poggit\curlPost;
use function poggit\getDb;
use function poggit\getLog;
use function poggit\getSecret;
use function poggit\ghApiGet;
use function poggit\headIncludes;

class HomePage extends Page {
    public function getName() : string {
        return "home";
    }

    public function output() {
        $session = new SessionUtils();
        $clientId = getSecret("app.clientId");
        $state = bin2hex(openssl_random_pseudo_bytes(16));
        $session->setAppState($state);
        if(!$session->hasLoggedIn()) {
            ?>
            <html>
            <head>
                <title>Poggit</title>
                <?php headIncludes() ?>
            </head>
            <body>
            <p>(Introduction here)</p>
            <p>
                <?php $url = "https://github.com/login/oauth/authorize?client_id=$clientId&state=$state&scope=user:email,write:repo_hook,repo"; ?>
                <a href="<?= $url ?>">Register/Login with GitHub</a>
            </p>
            </body>
            </html>
            <?php
        } else {
            $login = $session->getLogin();
            $name = $login["name"];
            ?>
            <html>
            <head>
                <title>Poggit</title>
                <?php headIncludes() ?>
            </head>
            <body>
            <h1 class="title">Poggit</h1>
            <hr>
            <header class="tagline">Welcome back, <?= $name ?>!</header>
            <h2>Configure repos</h2>
            <div class="wrapper">
                <?php
                $repos = ghApiGet("https://api.github.com/user/repos", $login["access_token"]);
                $accs = [];
                foreach($repos as $repo) {
                    $accs[$repo->owner->login][] = $repo;
                }
                uksort($accs, function ($a, $b) use ($accs, $login) {
                    if($a === $login["name"]) {
                        return -1;
                    }
                    if($b === $login["name"]) {
                        return 1;
                    }
                    return count($accs[$a]) <=> count($accs[$b]);
                });
                foreach($accs as $owner => $repos) {
                    ?>
                    <div class="toggle"
                         data-name="<?= $owner ?>" <?= $owner === $login["name"] ? "data-opened='true'" : "" ?>>
                        <table>
                            <tr>
                                <th>Repo</th>
                                <th>Poggit build</th>
                                <th>Poggit release</th>
                            </tr>
                            <?php
                            $repoIds = array_map(function ($repo) {
                                return "repoId = " . $repo->id;
                            }, $repos);
                            $query = "SELECT repoId,build,rel FROM repos WHERE " . implode(" OR ", $repoIds);
                            getLog()->d($query);
                            $result = getDb()->query($query);
                            $repoData = [];
                            while(is_array($row = $result->fetch_assoc())) {
                                $repoData[(int) $row["repoId"]] = [
                                    "build" => ((int) $row["build"]) > 0,
                                    "release" => ((int) $row["rel"]) > 0,
                                ];
                            }
                            $result->close();
                            foreach($repos as $repo) {
                                $isBuild = isset($repoData[$repo->id]) ? $repoData[$repo->id]["build"] : false;
                                $isRelease = isset($repoData[$repo->id]) ? $repoData[$repo->id]["release"] : false;
                                ?>
                                <tr>
                                    <td><a href="<?= $repo->html_url ?>"><?= htmlspecialchars($repo->name) ?></a></td>
                                    <td><input type="checkbox" class="bool-build" <?= $isBuild ? "checked" : "" ?>></td>
                                    <td><input type="checkbox" class="bool-release"<?= $isRelease ? "checked" : "" ?>>
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
                <?php
                $url = "https://github.com/login/oauth/authorize?client_id=$clientId&state=$state&scope=read:org";
                ?>
                <p>
                    Not showing all your organizations?
                    <a href="<?= $url ?>">Grant Poggit access to view all your organization memberships.</a>
                </p>
            </div>
            </body>
            </html>
            <?php
        }
    }
}
