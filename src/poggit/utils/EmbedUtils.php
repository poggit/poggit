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

namespace poggit\utils;

use poggit\Poggit;
use stdClass;

class EmbedUtils {
    public static function showBuildNumbers(int $global, int $internal, string $link = "") {
        if(strlen($link) > 0) { ?>
            <a href="<?= Poggit::getRootPath() . $link ?>">
        <?php } ?>
        <span style='font-family:"Courier New", monospace;'>
            #<?= $internal ?> (&amp;<?= strtoupper(dechex($global)) ?>)
        </span>
        <?php if(strlen($link) > 0) { ?>
            </a>
        <?php } ?>
        <sup class="hover-title" title="#<?= $internal ?> is the internal build number for your project.
&amp;<?= strtoupper(dechex($global)) ?> is a unique build ID for all Poggit CI builds">(?)</sup>
        <?php
    }

    public static function ghLink(string $url) {
        $markUrl = Poggit::getRootPath() . "res/ghMark.png";
        echo "<a href='$url' target='_blank'>";
        echo "<img class='gh-logo' src='$markUrl' width='16'/>";
        echo "</a>";
    }

    /**
     * @param string|stdClass $owner
     * @param string|int      $avatar
     * @param int             $avatarWidth
     */
    public static function displayUser($owner, $avatar = "", $avatarWidth = 16) {
        if($owner instanceof stdClass) {
            EmbedUtils::displayUser($owner->login, $owner->avatar_url, $avatar ?: 16);
            return;
        }
        if($avatar !== "") {
            echo "<img src='$avatar' width='$avatarWidth'/> ";
        }
        echo $owner, " ";
        EmbedUtils::ghLink("https://github.com/$owner");
    }

    public static function displayRepo(string $owner, string $repo, string $avatar = "", int $avatarWidth = 16) {
        EmbedUtils::displayUser($owner, $avatar, $avatarWidth);
        echo " / ";
        echo $repo, " ";
        EmbedUtils::ghLink("https://github.com/$owner/$repo");
    }

    public static function displayAnchor($name) {
        ?>
        <a class="dynamic-anchor" id="anchor-<?= $name ?>" name="<?= $name ?>" href="#<?= $name ?>">&sect;</a>
        <?php
    }
}
