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

namespace poggit\release;

use Composer\Semver\Comparator;
use poggit\account\Session;
use poggit\Config;
use poggit\Mbd;
use poggit\Meta;
use poggit\release\index\IndexPluginThumbnail;
use poggit\utils\internet\Mysql;
use poggit\utils\PocketMineApi;

class Release {
    const FLAG_PRE_RELEASE = 0x02;
    const FLAG_OUTDATED = 0x04; // Uses old API versions
    const FLAG_OFFICIAL = 0x08;
    const FLAG_OBSOLETE = 0x01; // This is not the latest version

    const STATE_DRAFT = 0;
    const STATE_REJECTED = 1;
    const STATE_SUBMITTED = 2;
    const STATE_CHECKED = 3;
    const STATE_VOTED = 4;
    const STATE_APPROVED = 5;
    const STATE_FEATURED = 6;
    public static $STATE_ID_TO_HUMAN = [
        Release::STATE_DRAFT => "Draft",
        Release::STATE_REJECTED => "Rejected",
        Release::STATE_SUBMITTED => "Submitted",
        Release::STATE_CHECKED => "Checked",
        Release::STATE_VOTED => "Voted",
        Release::STATE_APPROVED => "Approved",
        Release::STATE_FEATURED => "Featured"
    ];
    public static $STATE_SID_TO_ID = [
        "draft" => Release::STATE_DRAFT,
        "rejected" => Release::STATE_REJECTED,
        "submitted" => Release::STATE_SUBMITTED,
        "checked" => Release::STATE_CHECKED,
        "voted" => Release::STATE_VOTED,
        "approved" => Release::STATE_APPROVED,
        "featured" => Release::STATE_FEATURED
    ];

    const AUTHOR_LEVEL_COLLABORATOR = 1; // a person who is in charge of a major part of the plugin (whether having written code directly or not, or just merely designing the code structure)
    const AUTHOR_LEVEL_CONTRIBUTOR = 2; // a person who added minor changes to the plugin's code, but is not officially in the team writing the plugin
    const AUTHOR_LEVEL_TRANSLATOR = 3; // a person who only contributes translations or other non-code changes for the plugin
    const AUTHOR_LEVEL_REQUESTER = 4; // a person who provides abstract ideas for the plugin
    public static $AUTHOR_TO_HUMAN = [
        Release::AUTHOR_LEVEL_COLLABORATOR => "Collaborator",
        Release::AUTHOR_LEVEL_CONTRIBUTOR => "Contributor",
        Release::AUTHOR_LEVEL_TRANSLATOR => "Translator",
        Release::AUTHOR_LEVEL_REQUESTER => "Requester",
    ];


    public static $CATEGORIES = [
        1 => "General",
        2 => "Admin Tools",
        3 => "Informational",
        4 => "Anti-Griefing Tools",
        5 => "Chat-Related",
        6 => "Teleportation",
        7 => "Mechanics",
        8 => "Economy",
        9 => "Minigame",
        10 => "Fun",
        11 => "World Editing and Management",
        12 => "World Generators",
        13 => "Developer Tools",
        14 => "Educational",
        15 => "Miscellaneous",
    ];

    public static $PERMISSIONS = [
        1 => [
            "name" => "Manage plugins",
            "description" => "installs/uninstalls/enables/disables plugins"
        ],
        2 => [
            "name" => "Manage worlds",
            "description" => "registers worlds"
        ],
        3 => [
            "name" => "Manage permissions",
            "description" => "only includes managing user permissions for other plugins"
        ],
        4 => [
            "name" => "Manage entities",
            "description" => "registers new types of entities"
        ],
        5 => [
            "name" => "Manage blocks/items",
            "description" => "registers new blocks/items"
        ],
        6 => [
            "name" => "Manage tiles",
            "description" => "registers new tiles"
        ],
        7 => [
            "name" => "Manage world generators",
            "description" => "registers new world generators"
        ],
        8 => [
            "name" => "Database",
            "description" => "uses databases not local to this server instance, e.g. a MySQL database"
        ],
        9 => [
            "name" => "Other files",
            "description" => "uses SQLite databases and YAML data folders. Do not include non-data-saving fixed-number files (i.e. config & lang files)"],
        10 => [
            "name" => "Permissions",
            "description" => "registers permissions"
        ],
        11 => [
            "name" => "Commands",
            "description" => "registers commands"
        ],
        12 => [
            "name" => "Edit world",
            "description" => "changes blocks in a world; do not check this if your plugin only edits worlds using world generators"],
        13 => [
            "name" => "External Internet clients",
            "description" => "starts client sockets to the external Internet, including MySQL and cURL calls"
        ],
        14 => [
            "name" => "External Internet sockets",
            "description" => "listens on a server socket not started by PocketMine"
        ],
        15 => [
            "name" => "Asynchronous tasks",
            "description" => "uses AsyncTask"
        ],
        16 => [
            "name" => "Custom threading",
            "description" => "starts threads; does not include AsyncTask (because they aren't threads)"
        ],
    ];

    public static function validateName(string $name, string &$error = null): bool {
        if(!preg_match(/** @lang RegExp */
            '%^[A-Za-z0-9_.\-]{2,}$%', $name)) {
            $error = /** @lang HTML */
                "&cross; Plugin name must be at least two characters long, consisting of A-Z, a-z, 0-9, hyphen or underscore only";
            return false;
        }
        $rows = Mysql::query("SELECT COUNT(releases.name) AS dups FROM releases WHERE name = ? AND state >= ?", "si", $name, self::STATE_CHECKED);
        $dups = (int) $rows[0]["dups"];
        if($dups > 0) {
            $error = /** @lang HTML */
                "&cross; There are $dups other checked plugins with names starting with '$name'";
            return false;
        }
        $error = /** @lang HTML */
            "&checkmark; Great name!";
        return true;
    }

    public static function validateVersion(int $projectId, string $newVersion, string &$error = null): bool {
        if(!preg_match(/** @lang RegExp */
            '/^(0|[1-9]\d*)\.(0|[1-9]\d*)(\.(0|[1-9]\d*))?(-(0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*)(\.(0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*))*)?(\+[0-9a-zA-Z-]+(\.[0-9a-zA-Z-]+)*)?$/', $newVersion)) {
            $error = /** @lang HTML */
                "&cross; Version numbers must follow the Semantic Versioning scheme. Read the remarks above &uparrow;";
            return false;
        }
        $rows = Mysql::query("SELECT version FROM releases WHERE projectId = ? AND state >= ?", "ii", $projectId, self::STATE_SUBMITTED);
        foreach($rows as $row) {
            $oldVersion = $row["version"];
            $escOldVersion = htmlspecialchars($oldVersion);
            if(Comparator::equalTo($oldVersion, $newVersion)) {
                $error = /** @lang HTML */
                    "&cross; This plugin already has a version called $escOldVersion";
                return false;
            } elseif(Comparator::greaterThan($oldVersion, $newVersion)) {
                $error = "&cross; This plugin already has a version called <code>$escOldVersion</code>; you must only submit plugins with newer version names.";
                return false;
            }
        }
        $error = /** @lang HTML */
            "&checkmark; Excellent version name!";
        return true;
    }

    public static function showRecentPlugins(int $count) {
        foreach(self::getRecentPlugins($count) as $thumbnail) {
            echo '<div class="plugin-index">';
            self::pluginPanel($thumbnail);
            echo '</div>';
        }
    }

    /**
     * @param int $count
     * @return IndexPluginThumbnail[]
     */
    private static function getRecentPlugins(int $count): array {
        $result = [];
        $added = [];
        $session = Session::getInstance();
        $plugins = Mysql::query("SELECT
            releases.releaseId, releases.projectId AS projectId, releases.name, version, repos.owner AS author, shortDesc,
            icon, state, flags, private, dlCount AS downloads, projects.framework AS framework,
            UNIX_TIMESTAMP(releases.creation) AS created, UNIX_TIMESTAMP(updateTime) AS updateTime, IFNULL(scoreTotal, 0) scoreTotal, IFNULL(scoreCount, 0) scoreCount,
            (SELECT SUM(dlCount) FROM releases rel2 INNER JOIN resources rsr2 ON rel2.artifact = rsr2.resourceId WHERE rel2.projectId = releases.projectId) totalDl
            FROM releases
                INNER JOIN projects ON projects.projectId = releases.projectId
                INNER JOIN repos ON repos.repoId = projects.repoId
                INNER JOIN resources ON resources.resourceId = releases.artifact
                LEFT JOIN (SELECT releaseId, SUM(score) scoreTotal, COUNT(*) scoreCount FROM release_reviews GROUP BY releaseId) reviews ON releases.releaseId = reviews.releaseId
            WHERE state >= ?
            ORDER BY releases.updateTime DESC LIMIT $count", "i", self::STATE_VOTED);
        $adminLevel = Meta::getAdmlv($session->getName());
        foreach($plugins as $plugin) {
            if($session->getName() === $plugin["author"] ||
                (int) $plugin["state"] >= Config::MIN_PUBLIC_RELEASE_STATE ||
                ((int) $plugin["state"] >= self::STATE_CHECKED && $session->isLoggedIn()) ||
                ($adminLevel >= Meta::ADMLV_MODERATOR && (int) $plugin["state"] > self::STATE_DRAFT)) {
                $thumbNail = new IndexPluginThumbnail();
                $thumbNail->id = (int) $plugin["releaseId"];
                if(isset($added[$plugin["name"]])) continue;
                $thumbNail->projectId = (int) $plugin["projectId"];
                $thumbNail->name = $plugin["name"];
                $thumbNail->version = $plugin["version"];
                $thumbNail->author = $plugin["author"];
                $thumbNail->iconUrl = $plugin["icon"];
                $thumbNail->shortDesc = $plugin["shortDesc"];
                $thumbNail->creation = (int) $plugin["created"];
                $thumbNail->updateTime = (int) $plugin["updateTime"];
                $thumbNail->state = (int) $plugin["state"];
                $thumbNail->flags = (int) $plugin["flags"];
                $thumbNail->isPrivate = (int) $plugin["private"];
                $thumbNail->framework = $plugin["framework"];
                $thumbNail->isMine = $session->getName() === $plugin["author"];
                $thumbNail->dlCount = (int) $plugin["downloads"];
                $thumbNail->scoreTotal = (int) $plugin["scoreTotal"];
                $thumbNail->scoreCount = (int) $plugin["scoreCount"];
                $thumbNail->totalDl = (int) $plugin["totalDl"];
                $result[$thumbNail->id] = $thumbNail;
                $added[$thumbNail->name] = true;
            }
        }
        return $result;
    }

    public static function pluginPanel(IndexPluginThumbnail $plugin) {
        if(isset($plugin->scoreCount, $plugin->scoreTotal, $plugin->totalDl)) {
            $scores = [
                "total" => $plugin->scoreTotal,
                "average" => $plugin->scoreCount === 0 ? NAN : round($plugin->scoreTotal / $plugin->scoreCount, 1),
                "count" => $plugin->scoreCount,
                "totalDl" => $plugin->totalDl,
            ];
        } else {
            $scores = Mysql::query("SELECT SUM(release_reviews.score) AS score, COUNT(*) scoreCount FROM release_reviews
                INNER JOIN releases rel ON rel.releaseId = release_reviews.releaseId
                INNER JOIN projects p ON p.projectId = rel.projectId
                INNER JOIN repos r ON r.repoId = p.repoId
                WHERE rel.projectId = ? AND rel.state > 1 AND release_reviews.user <> r.accessWith", "i", $plugin->projectId);
            $totalDl = Mysql::query("SELECT SUM(rsr.dlCount) AS totalDl FROM resources rsr
                INNER JOIN releases rel ON rel.projectId = ?
                WHERE rsr.resourceId = rel.artifact", "i", $plugin->projectId);
            $scores = [
                "total" => $scores[0]["score"] ?? 0,
                "average" => round(($scores[0]["score"] ?? 0) / ((isset($scores[0]["scoreCount"]) && $scores[0]["scoreCount"] > 0) ? $scores[0]["scoreCount"] : 1), 1),
                "count" => $scores[0]["scoreCount"] ?? 0,
                "totalDl" => $totalDl[0]["totalDl"] ?? 0
            ];
        }
        ?>
      <div class="plugin-entry"
           data-state-change-date="<?= $plugin->updateTime ?>"
           data-submit-date="<?= $plugin->creation ?>"
           data-state="<?= $plugin->state ?>"
           data-downloads="<?= $plugin->dlCount ?>"
           data-total-downloads="<?= $plugin->totalDl ?>"
           data-mean-review="<?= $scores["average"] ?>"
           data-name="<?= $plugin->name ?>"
      >
        <div class="plugin-entry-block plugin-icon">
          <div class="plugin-image-wrapper">
            <a href="<?= Meta::root() ?>p/<?= urlencode($plugin->name) ?>/<?= urlencode($plugin->version) ?>">
              <img src="<?= Mbd::esq(($plugin->iconUrl && Session::getInstance()->showsIcons()) ? $plugin->iconUrl : (Meta::root() . "res/defaultPluginIcon2.png")) ?>"
                   width="56" height="56" title="<?= htmlspecialchars($plugin->shortDesc) ?>"/>
            </a>
          </div>
          <div class="smalldate-wrapper">
            <span class="plugin-smalldate"><?= htmlspecialchars(date('d M Y', $plugin->creation)) ?></span>
            <span class="plugin-smalldate"><?= $plugin->dlCount ?>/<?= $scores["totalDl"] ?>
              downloads</span>
              <?php
              if($scores["count"] > 0) { ?>
                  <div class="release-score">
                      <?php for($i = 0; $i < $scores["count"]; $i++) { ?><img
                          src="<?= Meta::root() ?>res/Full_Star_Yellow.svg" height="16"/><?php }
                      for($i = 0; $i < (5 - $scores["count"]); $i++) { ?><img
                          src="<?= Meta::root() ?>res/Empty_Star.svg" height="16"/><?php } ?>
                  </div>
              <?php } ?>
          </div>
        </div>
        <div class="plugin-entry-block plugin-main">
                <span class="plugin-name">
                    <a href="<?= Meta::root() ?>p/<?= htmlspecialchars($plugin->name) ?>/<?= $plugin->version ?>">
                            <?= htmlspecialchars($plugin->name) ?>
                    </a>
                    <?php self::printFlags($plugin->flags, $plugin->name) ?>
                </span>
          <span class="plugin-version">v<?= htmlspecialchars($plugin->version) ?></span>
          <span class="plugin-author"><?php Mbd::displayUser($plugin->author) ?></span>
        </div>
        <span
            class="plugin-state-<?= $plugin->state ?>"><?php echo htmlspecialchars(self::$STATE_ID_TO_HUMAN[$plugin->state]) ?></span>
        <div id="plugin-categories" value="<?= implode(",", $plugin->categories ?? []) ?>"></div>
        <div id="plugin-apis" value='<?= json_encode($plugin->spoons) ?>'></div>
      </div>
        <?php
    }

    public static function printFlags(int $flags, string $name) {
        if($flags & self::FLAG_OFFICIAL) echo '<span class="release-flag release-flag-official"
            title="This plugin is officially supported by PMMP/Poggit."></span>';
        if($flags & self::FLAG_PRE_RELEASE) echo "<span class='release-flag release-flag-pre-release'
            title='This is a pre-release.'></span>";
        $loginMessage = Session::getInstance()->isLoggedIn() ? "" : "More versions may be visible if you login." ;
        if($flags & self::FLAG_OBSOLETE) echo "<span class='release-flag release-flag-obsolete'
            title='This is not the latest version of $name. $loginMessage'></span>";
        $latest = PocketMineApi::LATEST_COMPAT;
        if($flags & self::FLAG_OUTDATED) echo "<span class='release-flag release-flag-outdated'
            title='This version only works on old versions of PocketMine-MP (before $latest).'></span>";
    }


    public static function getReviewQueue(int $maxState, int $count = 30, int $minState = 0, string $minAPI = null): array {
        $result = [];
        $session = Session::getInstance();
        $plugins = Mysql::query("SELECT
            r.releaseId, r.projectId AS projectId, r.name, r.version, rp.owner AS author, r.shortDesc,
            r.icon, r.state, r.flags, rp.private AS private, res.dlCount AS downloads, p.framework AS framework, UNIX_TIMESTAMP(r.creation) AS created, UNIX_TIMESTAMP(r.updateTime) AS updateTime,
            s.till FROM releases r
                INNER JOIN projects p ON p.projectId = r.projectId
                INNER JOIN repos rp ON rp.repoId = p.repoId
                INNER JOIN resources res ON res.resourceId = r.artifact
                INNER JOIN release_spoons s ON s.releaseId = r.releaseId
                WHERE ? <= state AND state <= ?
            ORDER BY flags & ? ASC, flags & ? ASC, state DESC, updateTime DESC LIMIT $count", "iiii", $minState, $maxState, self::FLAG_OBSOLETE, self::FLAG_OUTDATED);
        // Checked > Submitted; Updated > Obsolete; Compatible > Outdated; Latest > Oldest
        $admlv = Meta::getAdmlv($session->getName());
        foreach($plugins as $plugin) {
            if((int) $plugin["state"] >= Config::MIN_PUBLIC_RELEASE_STATE ||
                ((int) $plugin["state"] >= self::STATE_CHECKED && $session->isLoggedIn()) ||
                $admlv >= Meta::ADMLV_MODERATOR) {
                $thumbNail = new IndexPluginThumbnail();
                $thumbNail->id = (int) $plugin["releaseId"];
                if(isset($minAPI) && !Comparator::greaterThanOrEqualTo($plugin["till"], $minAPI)) {
                    continue;
                }
                if(isset($result[$thumbNail->id])) {
                    continue;
                }
                $thumbNail->projectId = (int) $plugin["projectId"];
                $thumbNail->name = $plugin["name"];
                $thumbNail->version = $plugin["version"];
                $thumbNail->author = $plugin["author"];
                $thumbNail->iconUrl = $plugin["icon"];
                $thumbNail->shortDesc = $plugin["shortDesc"];
                $thumbNail->creation = (int) $plugin["created"];
                $thumbNail->updateTime = (int) $plugin["updateTime"];
                $thumbNail->state = (int) $plugin["state"];
                $thumbNail->flags = (int) $plugin["flags"];
                $thumbNail->isPrivate = (int) $plugin["private"];
                $thumbNail->framework = $plugin["framework"];
                $thumbNail->isMine = $session->getName() === $plugin["author"];
                $thumbNail->dlCount = (int) $plugin["downloads"];
                $result[$thumbNail->id] = $thumbNail;
            }
        }
        return $result;
    }
}
