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

namespace poggit\model;

class PluginRelease {
    const RELEASE_REVIEW_CRITERIA_CODE_QUALITY = 1;
    const RELEASE_REVIEW_CRITERIA_PERFORMANCE = 2;
    const RELEASE_REVIEW_CRITERIA_USEFULNESS = 3;
    const RELEASE_REVIEW_CRITERIA_CONCEPT = 4;

    const RELEASE_STATE_UNSEEN = 0;

    const RELEASE_FLAG_FEATURED = 0x01;
    const RELEASE_FLAG_PRE_RELEASE = 0x02;

    const META_PERMISSION = 1;

    const RELEASE_STAGE_DRAFT = 0;
    const RELEASE_STAGE_UNCHECKED = 1;
    const RELEASE_STAGE_RESTRICTED = 2;
    const RELEASE_STAGE_TRUSTED = 3;
    const RELEASE_STAGE_APPROVED = 4;
    const RELEASE_STAGE_FEATURED = 5;


    public static $CATEGORIES = [
        1 => "Admin Tools",
        2 => "Anti-Griefing Tools",
        3 => "Chat-Related",
        4 => "Developer Tools",
        5 => "Economy",
        6 => "Educational",
        7 => "Fun",
        8 => "General",
        9 => "Informational",
        10 => "Mechanics",
        11 => "Miscellaneous",
        12 => "Teleportation",
        13 => "World Editing and Management",
        14 => "World Generators"
    ];

    public static $PERMISSIONS = [
        1 => ["Manage plugins", "installs/uninstalls/enables/disables plugins"],
        2 => ["Manage worlds", "registers worlds"],
        3 => ["Manage permissions", "only includes managing user permissions for other plugins"],
        4 => ["Manage entities", "register new types of entities"],
        5 => ["Manage blocks/items", "register new blocks/items"],
        6 => ["Manage tiles", "register new tiles"],
        7 => ["Manage world generators", "register new world generators"],
        8 => ["Database", "databases not local to this server instance, e.g. a MySQL database"],
        9 => ["Other files", "excludes non-data-saving definite-number files (i.e. config files and lang files), but includes SQLite databases and YAML data folders"],
        10 => ["Permissions", "registers permissions"],
        11 => ["Commands", "registers commands"],
        12 => ["Edit world", "changes blocks in a world, do not check this if only edits world from world generators"],
        13 => ["External Internet clients", "starts client sockets to the external Internet"],
        14 => ["External Internet sockets", "listens on a server socket not started by PocketMine"],
        15 => ["Asynchronous tasks", "uses AsyncTask"],
        16 => ["Custom threading", "starts threads, does not exclude AsyncTask (because they aren't threads)"],
    ];

    // requirements and enhancements are similar. both are things that cannot be defaulted but used in the config file
    // requirements are things that MUST exist before the FIRST time that the plugin runs.
    // enhancements are things that are OPTIONAL but the plugin still functions properly
    public static $REQUIREMENTS = [
        1 => "MySQL server", // if the plugin supports multiple database types, this is an enhancement not a requirement
        2 => "Mail server", // specify what mail server is required in the description
        3 => "API tokens", // e.g. GitHub API personal access tokens and other personal tokens retrieved from online services
        4 => "Internal password", // this plugin starts some kind of service that can only be accessed with a password
    ];

    /** @var string */
    public $name;
    /** @var string */
    public $shortDesc;
    /** @var int resId */
    public $artifact;
    /** @var int projectId */
    public $projectId;
    /** @var string */
    public $version;
    /** @var int resId */
    public $description;
    /** @var int resId */
    public $icon;
    /** @var int resId */
    public $changeLog;
    /** @var string licenseType */
    public $license;
    /** @var int ?resId */
    public $licenseRes;
    /** @var int bitmask RELEASE_FLAG_* */
    public $flags;
    /** @var int time() */
    public $creation;

    /** @var int[] orderSensitive */
    public $categories; // inherited from project
    /** @var string[] */
    public $keywords; // inherited from project
    /** @var string[] */
    public $dependencies;
    /** @var int[] */
    public $permissions;
    /** @var int[] */
    public $requirements;
    /** @var string[] spoon=>api */
    public $spoons;
}
