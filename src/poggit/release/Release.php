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
use poggit\release\details\review\PluginReview;
use poggit\release\index\IndexPluginThumbnail;
use poggit\utils\internet\Mysql;

class Release {
    const FLAG_PRE_RELEASE = 0x02;
    const FLAG_OUTDATED = 0x04;
    const FLAG_OFFICIAL = 0x08;

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
            "description" => "starts threads; do not include AsyncTask (because they aren't threads)"
        ],
    ];

    public static function validateName(string $name, string &$error = null): bool {
        if(!preg_match(/** @lang RegExp */
            '%^[A-Za-z0-9_.\-]{2,}$%', $name)) {
            $error = /** @lang HTML */
                "&cross; Plugin name must be at least two characters long, consisting of A-Z, a-z, 0-9, hyphen or underscore only";
            return false;
        }
        $rows = Mysql::query("SELECT COUNT(releases.name) AS dups FROM releases WHERE name = ? AND state >= ?", "si", $name, Release::STATE_CHECKED);
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
                "&cross; Version numbers must follow the Semantic Versioning scheme. Read the remarks below &downarrow;";
            return false;
        }
        $rows = Mysql::query("SELECT version FROM releases WHERE projectId = ? AND state >= ?", "ii", $projectId, Release::STATE_SUBMITTED);
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

    public static function pluginPanel(IndexPluginThumbnail $plugin) {
        $scores = PluginReview::getScores($plugin->projectId);
        ?>
        <div class="plugin-entry">
            <div class="plugin-entry-block plugin-icon">
                <div class="plugin-image-wrapper">
                    <a href="<?= Meta::root() ?>p/<?= urlencode($plugin->name) ?>/<?= urlencode($plugin->version) ?>">
                        <img src="<?= Mbd::esq($plugin->iconUrl ?? (Meta::root() . "res/defaultPluginIcon2.png")) ?>"
                             width="56" title="<?= htmlspecialchars($plugin->shortDesc) ?>"/>
                    </a>
                </div>
                <div class="smalldate-wrapper">
                    <span class="plugin-smalldate"><?= htmlspecialchars(date('d M Y', $plugin->creation)) ?></span>
                    <span class="plugin-smalldate"><?= $plugin->dlCount ?>/<?= $scores["totaldl"] ?>
                        downloads</span>
                    <?php
                    if($scores["count"] > 0) { ?>
                        <span class="plugin-smalldate">score <?= $scores["average"] ?>
                            /5 (<?= $scores["count"] ?>)</span>
                    <?php } ?>
                </div>
            </div>
            <div class="plugin-entry-block plugin-main">
                <a href="<?= Meta::root() ?>p/<?= htmlspecialchars($plugin->name) ?>/<?= $plugin->version ?>"><span
                            class="plugin-name"><?= htmlspecialchars($plugin->name) ?></span></a>
                <span class="plugin-version">Version <?= htmlspecialchars($plugin->version) ?></span>
                <span class="plugin-author"><?php Mbd::displayUser($plugin->author) ?></span>
            </div>
            <span class="plugin-state-<?= $plugin->state ?>"><?php echo htmlspecialchars(self::$STATE_ID_TO_HUMAN[$plugin->state]) ?></span>
            <div id="plugin-categories" value="<?= implode(",", $plugin->categories ?? []) ?>"></div>
            <div id="plugin-apis" value='<?= json_encode($plugin->spoons) ?>'></div>
        </div>
        <?php
    }

    public static function getRecentPlugins(int $count, bool $unique): array {
        $result = [];
        $added = [];
        $session = Session::getInstance();
        $plugins = Mysql::query("SELECT
            r.releaseId, r.projectId AS projectId, r.name, r.version, rp.owner AS author, r.shortDesc,
            r.icon, r.state, r.flags, rp.private AS private, res.dlCount AS downloads, p.framework AS framework, UNIX_TIMESTAMP(r.creation) AS created
            FROM releases r
                INNER JOIN projects p ON p.projectId = r.projectId
                INNER JOIN repos rp ON rp.repoId = p.repoId
                INNER JOIN resources res ON res.resourceId = r.artifact
            ORDER BY r.state DESC, r.updateTime DESC LIMIT $count");
        $adminlevel = Meta::getAdmlv($session->getName());
        foreach($plugins as $plugin) {
            if($session->getName() === $plugin["author"] || (int) $plugin["state"] >= Config::MIN_PUBLIC_RELEASE_STATE || (int) $plugin["state"] >= Release::STATE_CHECKED && $session->isLoggedIn() || ($adminlevel >= Meta::ADMLV_MODERATOR && (int) $plugin["state"] > Release::STATE_DRAFT)) {
                $thumbNail = new IndexPluginThumbnail();
                $thumbNail->id = (int) $plugin["releaseId"];
                if($unique && isset($added[$plugin["name"]])) continue;
                $thumbNail->projectId = (int) $plugin["projectId"];
                $thumbNail->name = $plugin["name"];
                $thumbNail->version = $plugin["version"];
                $thumbNail->author = $plugin["author"];
                $thumbNail->iconUrl = $plugin["icon"];
                $thumbNail->shortDesc = $plugin["shortDesc"];
                $thumbNail->creation = (int) $plugin["created"];
                $thumbNail->state = (int) $plugin["state"];
                $thumbNail->flags = (int) $plugin["flags"];
                $thumbNail->isPrivate = (int) $plugin["private"];
                $thumbNail->framework = $plugin["framework"];
                $thumbNail->isMine = $session->getName() === $plugin["author"];
                $thumbNail->dlCount = (int) $plugin["downloads"];
                $result[$thumbNail->id] = $thumbNail;
                $added[$thumbNail->name] = true;
            }
        }
        return $result;
    }

    public static function getPluginsByState(int $state, int $count = 30): array {
        $result = [];
        $session = Session::getInstance();
        $plugins = Mysql::query("SELECT
            r.releaseId, r.projectId AS projectId, r.name, r.version, rp.owner AS author, r.shortDesc,
            r.icon, r.state, r.flags, rp.private AS private, res.dlCount AS downloads, p.framework AS framework, UNIX_TIMESTAMP(r.creation) AS created
            FROM releases r
                INNER JOIN projects p ON p.projectId = r.projectId
                INNER JOIN repos rp ON rp.repoId = p.repoId
                INNER JOIN resources res ON res.resourceId = r.artifact
                WHERE state <= $state AND state > 0
            ORDER BY state DESC, updateTime DESC LIMIT $count");
        $adminlevel = Meta::getAdmlv($session->getName());
        foreach($plugins as $plugin) {
            if((int) $plugin["state"] >= Config::MIN_PUBLIC_RELEASE_STATE || ((int) $plugin["state"] >= Release::STATE_CHECKED && $session->isLoggedIn()) || $adminlevel >= Meta::ADMLV_MODERATOR) {
                $thumbNail = new IndexPluginThumbnail();
                $thumbNail->id = (int) $plugin["releaseId"];
                $thumbNail->projectId = (int) $plugin["projectId"];
                $thumbNail->name = $plugin["name"];
                $thumbNail->version = $plugin["version"];
                $thumbNail->author = $plugin["author"];
                $thumbNail->iconUrl = $plugin["icon"];
                $thumbNail->shortDesc = $plugin["shortDesc"];
                $thumbNail->creation = (int) $plugin["created"];
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
