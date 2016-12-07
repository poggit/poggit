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

use poggit\PocketMineApiInfo;
use poggit\Poggit;
use poggit\resource\ResourceManager;
use poggit\session\SessionUtils;

class PluginRelease {
    const MAX_SHORT_DESC_LENGTH = 128;
    const MAX_VERSION_LENGTH = 20;

    const RELEASE_REVIEW_CRITERIA_CODE_QUALITY = 1;
    const RELEASE_REVIEW_CRITERIA_PERFORMANCE = 2;
    const RELEASE_REVIEW_CRITERIA_USEFULNESS = 3;
    const RELEASE_REVIEW_CRITERIA_CONCEPT = 4;

    const RELEASE_STATE_UNSEEN = 0;

    const RELEASE_FLAG_PRE_RELEASE = 0x02;

    const META_PERMISSION = 1;

    const RELEASE_STAGE_DRAFT = 0;
    const RELEASE_STAGE_UNCHECKED = 1;
    const RELEASE_STAGE_RESTRICTED = 2;
    const RELEASE_STAGE_TRUSTED = 3;
    const RELEASE_STAGE_APPROVED = 4;
    const RELEASE_STAGE_FEATURED = 5;
    public static $STAGE_HUMAN = [
        PluginRelease::RELEASE_STAGE_DRAFT => "Draft",
        PluginRelease::RELEASE_STAGE_UNCHECKED => "Submitted",
        PluginRelease::RELEASE_STAGE_RESTRICTED => "Checked",
        PluginRelease::RELEASE_STAGE_TRUSTED => "Voted",
        PluginRelease::RELEASE_STAGE_APPROVED => "Approved",
        PluginRelease::RELEASE_STAGE_FEATURED => "Featured",
    ];

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

    /** @var string */
    public $name;
    /** @var string */
    public $shortDesc;
    /** @var int resId */
    public $artifact;
    /** @var int projectId */
    public $projectId;
    /** @var int buildId */
    public $buildId;
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
    /** @var int */
    public $stage;

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

    public static function validatePluginName(string $name, string &$error = null): bool {
        if(!preg_match('%^[A-Za-z0-9_]{2,}$%', $name)) {
            $error = "Plugin name must be at least two characters long, consisting of A-Z, a-z, 0-9 or _ only";
            return false;
        }
        $rows = Poggit::queryAndFetch("SELECT COUNT(releases.name) AS dups FROM releases WHERE name LIKE ? AND state >= ?", "si", $name . "%", PluginRelease::RELEASE_STAGE_RESTRICTED);
        $dups = (int) $rows[0]["dups"];
        if($dups > 0) {
            $error = "There are $dups other checked plugins with names starting with '$name'";
            return false;
        }
        $error = "Great name!";
        return true;
    }

    public static function fromSubmitJson(\stdClass $data): PluginRelease {
        $instance = new PluginRelease;

        if(!isset($data->buildId)) throw new SubmitException("Param 'buildId' missing");
        $rows = Poggit::queryAndFetch("SELECT p.repoId, b.projectId, b.internal FROM builds b
            INNER JOIN projects p ON b.projectId = p.projectId WHERE b.buildId = ?", "i", $data->buildId);
        if(count($rows) === 0) throw new SubmitException("Param 'buildId' does not represent a valid build");
        $build = $rows[0];
        unset($rows);
        $repoId = (int) $build["repoId"];
        try {
            $repo = Poggit::ghApiGet("repositories/$repoId", $token = SessionUtils::getInstance()->getAccessToken());
            if(!isset($repo->permissions) or !$repo->permissions->admin) throw new \Exception;
        } catch(\Exception $e) {
            throw new SubmitException("Admin access required for releasing plugins");
        }
        $instance->projectId = (int) $build["projectId"];
        $instance->buildId = (int) $data->buildId;

        $prevRows = Poggit::queryAndFetch("SELECT version FROM releases WHERE projectId = ?", "i", $instance->projectId);
        $prevVersions = array_map(function ($row) {
            return $row["version"];
        }, $prevRows);
        $update = count($prevVersions) > 0;

        if(!isset($data->name)) throw new SubmitException("Param 'name' missing");
        $name = $data->name;
        if(!PluginRelease::validatePluginName($name, $error)) throw new SubmitException("Invalid plugin name: $error");
        $instance->name = $name;

        if(!isset($data->shortDesc)) throw new SubmitException("Param 'shortDesc' missing");
        if(strlen($data->shortDesc) > PluginRelease::MAX_SHORT_DESC_LENGTH) throw new SubmitException("Param 'shortDesc' is too long");
        $instance->shortDesc = $data->shortDesc;

        if(!isset($data->version)) throw new SubmitException("Param 'version' missing");
        if(strlen($data->version) > PluginRelease::MAX_VERSION_LENGTH) throw new SubmitException("Version is too long");
        if($update and in_array($data->version, $prevVersions)) throw new SubmitException("This version name has already been used for your plugin!");
        $instance->version = $data->version;

        if(!isset($data->description) or !($data->description instanceof \stdClass)) throw new SubmitException("Param 'description' missing or incorrect");
        $description = $data->description;
        $descRsr = PluginRelease::storeArticle($repo->full_name, $description, "description");
        $instance->description = $descRsr;

        if($update) {
            if(!isset($data->changeLog)) throw new SubmitException("Param 'changeLog' missing");
            if($data->changeLog instanceof \stdClass) {
                $changeLog = $data->changeLog;
                $clRsr = PluginRelease::storeArticle($repo->full_name, $changeLog);
                $instance->changeLog = $clRsr;
            } else $instance->changeLog = ResourceManager::NULL_RESOURCE;
        } else $instance->changeLog = ResourceManager::NULL_RESOURCE;

        if(!isset($data->license->type)) throw new SubmitException("Param 'license' missing or incorrect");
        $license = $data->license;
        $type = strtolower($license->type);
        if($type === "custom") {
            if(!isset($license->val)) throw new SubmitException("Param 'license' missing custom value");
            $licRsr = PluginRelease::storeArticle($repo->full_name, $license->val, "custom license");
            $instance->license = "custom";
            $instance->licenseRes = $licRsr;
        } elseif($type === "none") {
            $instance->license = "none";
        } else {
            $licenseData = Poggit::ghApiGet("licenses", $token, ["Accept: application/vnd.github.drax-preview+json"]);
            foreach($licenseData as $datum) {
                if($datum->key === $type) {
                    $instance->license = $datum->key;
                    break;
                }
            }
            if(!isset($instance->license)) throw new SubmitException("Param 'license' contains unknown field");
        }

        $instance->flags = ($data->preRelease ?? false) ? PluginRelease::RELEASE_FLAG_PRE_RELEASE : 0;

        if(!isset($data->categories->major, $data->categories->minor)) throw new SubmitException("Param 'categorioes' missing");
        $cats = $data->categories;
        if(!isset(PluginRelease::$CATEGORIES[$cats->major])) throw new SubmitException("Unknown category $cats->major");
        $instance->categories = [$cats->major];
        foreach($cats->minor as $cat) {
            if(!isset(PluginRelease::$CATEGORIES[$cats->minor])) throw new SubmitException("Unknwon category $cat");
            $instance->categories[] = $cat;
        }

        if(!isset($data->keywords)) throw new SubmitException("Param 'categorioes' missing");
        $keywords = [];
        foreach($data->keywords as $keyword) {
            if(strlen($keyword) === 0) continue;
            // TODO more censorship on keywords
            $keywords[] = $keyword;
            if(count($keywords) > 25) throw new SubmitException("Param 'keywords' missing");
        }
        $instance->keywords = $keywords;

        if(!isset($data->spoons)) throw new SubmitException("Param 'spoons' missing");
        foreach($data->spoons as $i => $entry) {
            if(!isset($entry->api)) throw new SubmitException("Param spoons[$i] missing property api");
            if(count($entry->api) !== 2) throw new SubmitException("Param spoons[$i].api is invalid");
            list($api0, $api1) = $entry->api;
            $apis = [self::searchApiByString($api0) => $api0, self::searchApiByString($api1) => $api1];
            ksort($apis, SORT_NUMERIC);
            $instance->spoons[] = array_values($apis);
        }

        foreach($data->deps ?? [] as $i => $dep) {
            if(!isset($dep->name, $dep->version, $dep->softness)) throw new SubmitException("Param deps[$i] is incorrect");
            if($dep->name === "poggit-release") {
                $rows = Poggit::queryAndFetch("SELECT releaseId, name, version FROM releases WHERE releaseId = ?", "i", $dep->version);
                if(count($rows) === 0) throw new SubmitException("Param deps[$i] declares invalid dependency");
                $depName = $rows[0]["name"];
                $depVersion = $rows[0]["version"];
                $depRelId = $rows[0]["releaseId"];
            } else {
                $depName = $dep->name;
                $depVersion = $dep->version;
                $depRelId = null;
            }
            $instance->dependencies[] = ["name" => $depName, "version" => $depVersion, "depRelId" => $depRelId, "isHard" => $dep->softness === "hard"];
        }

        if(!isset($data->perms)) throw new SubmitException("Param 'perms' missing");
        foreach($data->perms as $perm) {
            if(!isset(PluginRelease::$PERMISSIONS)) throw new SubmitException("Unknown perm $perm");
            $instance->permissions[] = $perm;
        }

        foreach($data->reqr ?? [] as $i => $reqr) {
            if(!isset($reqr->type, $reqr->enhance)) throw new SubmitException("Param reqr[$i] incorrect");
            $type = $reqr->type;
            if(!in_array($type, ["mail", "mysql", "apiToken", "password", "other"])) throw new SubmitException("Unknown requirement type $type");
            $details = $reqr->details ?? "";
            $isRequired = $reqr->enhance === "requirement";

            $instance->requirements[] = ["type" => $type, "details" => $details, "isRequire" => $isRequired];
        }

        $instance->stage = $data->asDraft ? PluginRelease::RELEASE_STAGE_DRAFT : PluginRelease::RELEASE_STAGE_UNCHECKED;

        return $instance;
    }

    private static function storeArticle(string $ctx, \stdClass $data, string $field = null): int {
        $type = $data->type ?? "md";
        $value = $data->text ?? "";

        if($field !== null and strlen($value) < 10) throw new SubmitException("Please write a proper $field for your plugin! Your description is far too short!");

        if($type === "txt") {
            $file = ResourceManager::getInstance()->createResource("txt", "text/plain", [], $rid);
            file_put_contents($file, htmlspecialchars($value));
        } elseif($type === "md") {
            $data = Poggit::ghApiPost("markdown", ["text" => $value, "mode" => "gfm", "context" => $ctx],
                SessionUtils::getInstance()->getAccessToken(), true, ["Accept: application/vnd.github.v3"]);
            $file = ResourceManager::getInstance()->createResource("html", "text/html", [], $rid);
            file_put_contents($file, $data);
        } else {
            throw new SubmitException("Unknown type '$type'");
        }
        return $rid;
    }

    private static function searchApiByString(string $api): int {
        $result = array_search($api, array_keys(PocketMineApiInfo::$VERSIONS));
        if($result === false) throw new SubmitException("Unknown API version $api");
        return $result;
    }

    public function submit(): int {
        $buildId = Poggit::queryAndFetch("INSERT INTO releases 
            (name, shortDesc, artifact, projectId, buildId, version, description, changelog, license, licenseRes, flags, creation, state) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", str_replace(" ", "",
            " s        s         i          i         i        s          i           i         s          i        i       i        i  "),
            $this->name, $this->shortDesc, $this->artifact, $this->projectId, $this->buildId, $this->version, $this->description, $this->changeLog, $this->license, $this->licenseRes, $this->flags, $this->creation, $this->stage)->insert_id;
        $this->buildId = $buildId;
        return $buildId;
    }
}
