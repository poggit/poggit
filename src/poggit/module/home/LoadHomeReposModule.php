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

use poggit\module\ajax\AjaxModule;
use poggit\output\OutputManager;
use poggit\Poggit;
use poggit\session\SessionUtils;

class LoadHomeReposModule extends AjaxModule {
    protected function impl() {
        header("Content-Type: text/html");
        $minifier = OutputManager::startMinifyHtml();
        $login = SessionUtils::getInstance()->getLogin();
        $repos = Poggit::ghApiGet("user/repos?per_page=80", $login["access_token"]);
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
        $result = Poggit::queryAndFetch("SELECT repoId, buildFROM repos WHERE " . implode(" OR ", $repoIds));
        $repoData = [];
        foreach($result as $row) {
            $repoData[(int) $row["repoId"]] = [
                "build" => ((int) $row["build"]) > 0,
            ];
        }
        uksort($accs, function ($a, $b) use ($accs, $login) {
            if($a === $login["name"]) {
                return -1;
            }
            if($b === $login["name"]) {
                return 1;
            }
            $aRepos = $accs[$a];
            $bRepos = $accs[$b];
            return count($aRepos) <=> count($bRepos);
        });
        foreach($accs as $owner => $repos) {
            ?>
            <div class="toggle" data-name="<?= $owner ?>"
                <?= $owner === $login["name"] ? "data-opened='true'" : "" ?>>
                <table class="info-table">
                    <tr style="padding: 5px;">
                        <th>Repo</th>
                        <th>Poggit CI</th>
<!--                        <th>Poggit release</th>-->
                    </tr>
                    <?php
                    foreach($repos as $repo) {
                        $isBuild = isset($repoData[$repo->id]) ? $repoData[$repo->id]["build"] : false;
//                        $isRelease = isset($repoData[$repo->id]) ? $repoData[$repo->id]["release"] : false;
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
                                <a href="<?= Poggit::getRootPath() ?>ci/<?= $repo->owner->login ?>/<?= $repo->name ?>">Go
                                    to page</a>
                            </td>
<!--                            <td>-->
<!--                                <input type="checkbox" class="repo-boolean" data-type="release"-->
<!--                                       data-repo="--><?//= $repo->id ?><!--"-->
<!--                                    --><?//= $isRelease ? "checked" : "" ?>
<!--                                    --><?php //if($repo->private) { ?>
<!--                                        disabled-->
<!--                                        title="Private repos cannot have releases"-->
<!--                                    --><?php //} else { ?>
<!--                                        data-depends="--><?//= $rand ?><!--"-->
<!--                                    --><?php //} ?>
<!--                                >-->
<!--                                <a href="--><?//= Poggit::getRootPath() ?><!--plugins/in/--><?//= $repo->owner->login ?><!--/--><?//= $repo->name ?><!--">Go-->
<!--                                    to page</a>-->
<!--                            </td>-->
                        </tr>
                        <?php
                    }
                    ?>
                </table>
            </div>
            <?php
        }
        OutputManager::endMinifyHtml($minifier);
    }

    public function getName() : string {
        return "home.repos";
    }
}
