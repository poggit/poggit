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

use poggit\module\releases\index\IndexPluginThumbnail;
use poggit\resource\ResourceManager;
use poggit\resource\ResourceNotFoundException;
use poggit\utils\internet\CurlUtils;
use poggit\utils\internet\GitHubAPIException;
use poggit\utils\internet\MysqlUtils;
use poggit\utils\PocketMineApi;
use poggit\utils\SessionUtils;
use poggit\embed\EmbedUtils;
use poggit\Poggit;

class PluginRelease {
    const MAX_SHORT_DESC_LENGTH = 128;
    const MAX_VERSION_LENGTH = 20;
    const MAX_KEYWORD_COUNT = 100;
    const MAX_LICENSE_LENGTH = 51200;

    const RELEASE_REVIEW_CRITERIA_GENERAL = 0;
    const RELEASE_REVIEW_CRITERIA_CODE_QUALITY = 1;
    const RELEASE_REVIEW_CRITERIA_PERFORMANCE = 2;
    const RELEASE_REVIEW_CRITERIA_USEFULNESS = 3;
    const RELEASE_REVIEW_CRITERIA_CONCEPT = 4;
    public static $CRITERIA_HUMAN = [
        PluginRelease::RELEASE_REVIEW_CRITERIA_GENERAL => "General",
        PluginRelease::RELEASE_REVIEW_CRITERIA_CODE_QUALITY => "Code",
        PluginRelease::RELEASE_REVIEW_CRITERIA_PERFORMANCE => "Performance",
        PluginRelease::RELEASE_REVIEW_CRITERIA_USEFULNESS => "Usefulness",
        PluginRelease::RELEASE_REVIEW_CRITERIA_CONCEPT => "Concept"
    ];
    public static $REVIEW_TYPE = [
        1 => "Official",
        2 => "User",
        3 => "Robot"
    ];

    const RELEASE_FLAG_PRE_RELEASE = 0x02;
    const RELEASE_FLAG_OUTDATED = 0x04;
    const RELEASE_FLAG_OFFICIAL = 0x08;

    const META_PERMISSION = 1;

    const RELEASE_STAGE_DRAFT = 0;
    const RELEASE_STAGE_REJECTED = 1;
    const RELEASE_STAGE_SUBMITTED = 2;
    const RELEASE_STAGE_CHECKED = 3;
    const RELEASE_STAGE_VOTED = 4;
    const RELEASE_STAGE_APPROVED = 5;
    const RELEASE_STAGE_FEATURED = 6;

    public static $STAGE_HUMAN = [
        PluginRelease::RELEASE_STAGE_DRAFT => "Draft",
        PluginRelease::RELEASE_STAGE_REJECTED => "Rejected",
        PluginRelease::RELEASE_STAGE_SUBMITTED => "Submitted",
        PluginRelease::RELEASE_STAGE_CHECKED => "Checked",
        PluginRelease::RELEASE_STAGE_VOTED => "Voted",
        PluginRelease::RELEASE_STAGE_APPROVED => "Approved",
        PluginRelease::RELEASE_STAGE_FEATURED => "Featured"
    ];

    const MIN_PUBLIC_RELSTAGE = self::RELEASE_STAGE_CHECKED;

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
        1 => ["Manage plugins", "installs/uninstalls/enables/disables plugins"],
        2 => ["Manage worlds", "registers worlds"],
        3 => ["Manage permissions", "only includes managing user permissions for other plugins"],
        4 => ["Manage entities", "registers new types of entities"],
        5 => ["Manage blocks/items", "registers new blocks/items"],
        6 => ["Manage tiles", "registers new tiles"],
        7 => ["Manage world generators", "registers new world generators"],
        8 => ["Database", "uses databases not local to this server instance, e.g. a MySQL database"],
        9 => ["Other files", "uses SQLite databases and YAML data folders. Do not include non-data-saving fixed-number files (i.e. config & lang files)"],
        10 => ["Permissions", "registers permissions"],
        11 => ["Commands", "registers commands"],
        12 => ["Edit world", "changes blocks in a world; do not check this if your plugin only edits worlds using world generators"],
        13 => ["External Internet clients", "starts client sockets to the external Internet, including MySQL and cURL calls"],
        14 => ["External Internet sockets", "listens on a server socket not started by PocketMine"],
        15 => ["Asynchronous tasks", "uses AsyncTask"],
        16 => ["Custom threading", "starts threads; do not include AsyncTask (because they aren't threads)"],
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
    /** @var string url */
    public $icon;
    /** @var int resId */
    public $changeLog;
    /** @var string licenseText */
    public $licenseText;
    /** @var string licenseType */
    public $licenseType;
    /** @var int ?resId */
    public $licenseRes;
    /** @var int bitmask RELEASE_FLAG_* */
    public $flags;
    /** @var int time() */
    public $creation;
    /** @var int */
    public $stage;
    /** @var int */
    public $existingReleaseId;

    /** @var int */
    public $mainCategory;
    /** @var int[] orderSensitive */
    public $categories; // inherited from project
    /** @var string[] */
    public $keywords; // inherited from project
    /** @var PluginDependency[] */
    public $dependencies;
    /** @var int[] */
    public $permissions;
    /** @var PluginRequirement[] */
    public $requirements;
    /** @var string[][] spoon => [api0,api1] */
    public $spoons;
    /** @var int */
    public $existingState;

    public static function validatePluginName(string $name, string &$error = null): bool {
        if(!preg_match('%^[A-Za-z0-9_]{2,}$%', $name)) {
            $error = "Plugin name must be at least two characters long, consisting of A-Z, a-z, 0-9 or _ only";
            return false;
        }
        $rows = MysqlUtils::query("SELECT COUNT(releases.name) AS dups FROM releases WHERE name LIKE ? AND state >= ?", "si", $name . "%", PluginRelease::RELEASE_STAGE_CHECKED);
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
        $rows = MysqlUtils::query("SELECT p.repoId, p.path, b.projectId, b.branch, b.internal, b.resourceId, IFNULL(b.sha, b.branch) AS ref
            FROM builds b INNER JOIN projects p ON b.projectId = p.projectId WHERE b.buildId = ?", "i", $data->buildId);
        if(count($rows) === 0) throw new SubmitException("Param 'buildId' does not represent a valid build");
        $build = $rows[0];
        $buildArtifactId = (int) $build["resourceId"];
        unset($rows);
        $repoId = (int) $build["repoId"];
        $session = SessionUtils::getInstance();
        try {
            $repo = CurlUtils::ghApiGet("repositories/$repoId", $token = $session->getAccessToken());
            if((!isset($repo->permissions) or !$repo->permissions->admin) && Poggit::getAdmlv($session->getName()) < Poggit::MODERATOR) throw new \Exception;
        } catch(\Exception $e) {
            throw new SubmitException("Admin access required for releasing plugins");
        }
        $instance->projectId = (int) $build["projectId"];
        $instance->buildId = (int) $data->buildId;
        if(isset($data->iconName)) {
            $icon = PluginRelease::findIcon($repo->full_name, $build["path"] . $data->iconName, $build["ref"], $token);
            $instance->icon = is_object($icon) ? $icon->url : null;
        } else {
            $instance->icon = null;
        }

        $prevRows = MysqlUtils::query("SELECT version FROM releases WHERE projectId = ?", "i", $instance->projectId);
        $prevVersions = array_map(function ($row) {
            return $row["version"];
        }, $prevRows);
        $update = count($prevVersions) > 0;

        if(!isset($data->name)) throw new SubmitException("Param 'name' missing");
        $name = $data->name;
        if(!$update && !PluginRelease::validatePluginName($name, $error)) throw new SubmitException("Invalid plugin name: $error");
        $instance->name = $name;

        if(!isset($data->shortDesc)) throw new SubmitException("Param 'shortDesc' missing");
        if(strlen($data->shortDesc) > PluginRelease::MAX_SHORT_DESC_LENGTH) throw new SubmitException("Param 'shortDesc' is too long");
        $instance->shortDesc = $data->shortDesc;

        if(!isset($data->version)) throw new SubmitException("Param 'version' missing");
        if(strlen($data->version) > PluginRelease::MAX_VERSION_LENGTH) throw new SubmitException("Version is too long");
        if($update and in_array($data->version, $prevVersions)) throw new SubmitException("This version name has already been used for your plugin!");
        $instance->version = $data->version;

        if(!isset($data->desc) or !($data->desc instanceof \stdClass)) throw new SubmitException("Param 'desc' missing or incorrect");
        $description = $data->desc;
        $descResRow = MysqlUtils::query("SELECT description FROM releases WHERE buildId = ? LIMIT 1", "i", $data->buildId);
        if (count($descResRow) > 0) $descResId = (int) $descResRow[0]["description"];
        $descRsr = PluginRelease::storeArticle($descResId ?? null, $repo->full_name, $description, "description");
        $instance->description = $descRsr;

        if($update) {
            if(!isset($data->changeLog)) throw new SubmitException("Param 'changeLog' missing");
            if($data->changeLog instanceof \stdClass) {
                $changeLog = $data->changeLog;
                $clResId = null;
                $clResRow = MysqlUtils::query("SELECT changelog FROM releases WHERE buildId = ? LIMIT 1", "i", $data->buildId);
                if (count($clResRow) > 0) $clResId = (int) $clResRow[0]["changelog"] !== ResourceManager::NULL_RESOURCE ? (int) $clResRow[0]["changelog"] : null;
                $clRsr = PluginRelease::storeArticle($clResId, $repo->full_name, $changeLog, "changelog");
                $instance->changeLog = $clRsr;
            } else $instance->changeLog = ResourceManager::NULL_RESOURCE;
        } else $instance->changeLog = ResourceManager::NULL_RESOURCE;

        if(!isset($data->license) or !($data->license instanceof \stdClass)) throw new SubmitException("Param 'license' missing or incorrect");
        $license = $data->license;
        $type = strtolower($license->type);
        if($type === "custom") {
            $license->type = "txt";
            if(!isset($license->text) || strlen($license->text) > PluginRelease::MAX_LICENSE_LENGTH) throw new SubmitException("Custom licence text is empty or invalid");
                $licResId = null;
                $licenseResRow = MysqlUtils::query("SELECT licenseRes FROM releases WHERE buildId = ? LIMIT 1", "i", $data->buildId);
                if (count($licenseResRow) > 0) $licResId = (int) $licenseResRow[0]["licenseRes"];
                $licRsr = PluginRelease::storeArticle($licResId, $repo->full_name, $license, "custom license");
                $instance->licenseText = $license->text;
                $instance->licenseType = "custom";
                $instance->licenseRes = $licRsr;
        } elseif($type === "none") {
            $instance->licenseType = "none";
        } else {
            $licenseData = CurlUtils::ghApiGet("licenses", $token, ["Accept: application/vnd.github.drax-preview+json"]);
            foreach($licenseData as $datum) {
                if($datum->key === $type) {
                    $instance->licenseType = $datum->key;
                    break;
                }
            }
            if(!isset($instance->licenseType)) throw new SubmitException("Param 'license' contains unknown field");
        }

        $instance->flags = ($data->preRelease ?? false) ? PluginRelease::RELEASE_FLAG_PRE_RELEASE : 0;

        if(!isset($data->categories->major, $data->categories->minor)) throw new SubmitException("Param 'categories' missing");
        $cats = $data->categories;
        if(!isset(PluginRelease::$CATEGORIES[$cats->major])) throw new SubmitException("Unknown category $cats->major");
        $instance->mainCategory = (int) $cats->major;
        foreach($cats->minor as $cat) {
            if(!isset(PluginRelease::$CATEGORIES[$cat])) throw new SubmitException("Unknown category $cat");
            $instance->categories[] = $cat;
        }

        if(!isset($data->keywords)) throw new SubmitException("Param 'keywords' missing");
        $data->keywords = array_filter($data->keywords);
        $keywords = [];
        if(count($data->keywords) === 0) throw new SubmitException("Please enter at least one keyword so that others can search for your plugin!");
        if(count($data->keywords) > PluginRelease::MAX_KEYWORD_COUNT) $data->keywords = array_slice($data->keywords, 0, PluginRelease::MAX_KEYWORD_COUNT);
        foreach($data->keywords as $keyword) {
            if(strlen($keyword) === 0) continue;
            // TODO more censorship on keywords
            $keywords[] = $keyword;
        }
        $instance->keywords = $keywords;

        if(!isset($data->spoons)) throw new SubmitException("Param 'spoons' missing");
        if(count($data->spoons) === 0) throw new SubmitException("You should at least declare one compatible API version!");
        foreach($data->spoons as $i => $entry) {
            if(!isset($entry->api)) throw new SubmitException("Param spoons[$i] missing property api");
            if(count($entry->api) !== 2) throw new SubmitException("Param spoons[$i].api is invalid");
            list($api0, $api1) = $entry->api;
            if($api0 != $api1) {
                $apis = [self::searchApiByString($api0) => $api0, self::searchApiByString($api1) => $api1];
                ksort($apis, SORT_NUMERIC);
                $instance->spoons[] = array_values($apis);
            } else {
                $instance->spoons[] = [$api0, $api1];
            }

        }
        $instance->dependencies = [];
        foreach($data->deps ?? [] as $i => $dep) {
            if(!isset($dep->name, $dep->version, $dep->softness)) throw new SubmitException("Param deps[$i] is incorrect");
            if($dep->name === "poggit-release") {
                $rows = MysqlUtils::query("SELECT releaseId, name, version FROM releases WHERE releaseId = ?", "i", $dep->version);
                if(count($rows) === 0) throw new SubmitException("Param deps[$i] declares invalid dependency");
                $depName = $rows[0]["name"];
                $depVersion = $rows[0]["version"];
                $depRelId = $rows[0]["releaseId"];
            } else {
                $depName = $dep->name;
                $depVersion = $dep->version;
                $depRelId = 0;
            }
            $instance->dependencies[] = new PluginDependency($depName, $depVersion, $depRelId, $dep->softness === "hard");
        }

        if(!isset($data->perms)) throw new SubmitException("Param 'perms' missing");
        $instance->permissions = [];
        foreach($data->perms as $perm) {
            if(!isset(PluginRelease::$PERMISSIONS)) throw new SubmitException("Unknown perm $perm");
            $instance->permissions[] = $perm;
        }

        $instance->requirements = [];
        foreach($data->reqr ?? [] as $reqr) {
            $instance->requirements[] = PluginRequirement::fromJson($reqr);
        }

        $releases = MysqlUtils::query("SELECT buildId, releaseId, state FROM releases WHERE projectId = ? ORDER BY state DESC", "i", $instance->projectId);
        foreach ($releases as $release){
            if($release["buildId"] == $instance->buildId){
            $instance->existingReleaseId = (int) $release["releaseId"];
            $instance->existingState = (int) $release["state"];
            }
        }

        $newstate = PluginRelease::RELEASE_STAGE_SUBMITTED;
        if($data->asDraft) {
            $newstate = PluginRelease::RELEASE_STAGE_DRAFT;
        } else {
            if($instance->existingState && $instance->existingState > PluginRelease::RELEASE_STAGE_SUBMITTED) {
                $newstate = $instance->existingState;
            }
        }
        $instance->stage = $newstate;

        // prepare artifact at last step to save memory
        $artifact = PluginRelease::prepareArtifactFromResource($buildArtifactId, $instance->version);
        $instance->artifact = $artifact;
        return $instance;
    }

    private static function storeArticle(int $resourceId = null, string $ctx, \stdClass $data, string $field = null): int {
        $type = $data->type ?? "md";
        $value = $data->text ?? "";
        $rid = null;
        if($field !== null and strlen($value) < 10) throw new SubmitException("Please write a proper $field for your plugin! Your description is far too short!");

        if($type === "txt") {
            $file = $resourceId ? ResourceManager::getInstance()->pathTo($resourceId, "txt") : ResourceManager::getInstance()->createResource("txt", "text/plain", [], $rid);
            file_put_contents($file, htmlspecialchars($value));
            if ($resourceId !== null){
                MysqlUtils::query("UPDATE resources SET type = ?, mimeType = ? WHERE resourceId = ?", "ssi", "txt", "text/plain", $resourceId);
            }
        } elseif($type === "md") {
            $data = CurlUtils::ghApiPost("markdown", ["text" => $value, "mode" => "gfm", "context" => $ctx],
                SessionUtils::getInstance()->getAccessToken(), true, ["Accept: application/vnd.github.v3"]);
            $file = $resourceId ? ResourceManager::getInstance()->pathTo($resourceId, "html") : ResourceManager::getInstance()->createResource("html", "text/html", [], $rid);
            file_put_contents($file, $data);
            if ($resourceId !== null){
                MysqlUtils::query("UPDATE resources SET type = ?, mimeType = ? WHERE resourceId = ?", "ssi", "html", "text/html", $resourceId);

            }
        } else {
            throw new SubmitException("Unknown type '$type'");
        }
        return $rid ? $rid : $resourceId;
    }

    private static function searchApiByString(string $api): int {
        $result = array_search($api, array_keys(PocketMineApi::$VERSIONS));
        if($result === false) throw new SubmitException("Unknown API version $api");
        return $result;
    }

    private static function prepareArtifactFromResource(int $oldId, string $version): int {
        $file = ResourceManager::getInstance()->createResource("phar", "application/octet-stream", [], $newId);
        try {
            copy(ResourceManager::getInstance()->getResource($oldId, "phar"), $file);
        } catch(ResourceNotFoundException$e) {
            throw new SubmitException("Build already deleted");
        }
        $phar = new \Phar($file);
        $phar->startBuffering();
        $yaml = yaml_parse(file_get_contents($phar["plugin.yml"]->getPathName()));
        $yaml["version"] = $version;
        $phar["plugin.yml"] = yaml_emit($yaml, YAML_UTF8_ENCODING, YAML_LN_BREAK);
        $phar->stopBuffering();
        return $newId;
    }

    public function submit(): string {
        if (!isset($this->existingReleaseId)){
            $releaseId = MysqlUtils::query("INSERT INTO releases 
            (name, shortDesc, artifact, projectId, buildId, version, description, changelog, license, licenseRes, flags, creation, state, icon) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", str_replace(" ", "",
            " s        s         i          i         i        s          i           i         s          i        i       i        i     s  "),
            $this->name, $this->shortDesc, $this->artifact, $this->projectId, $this->buildId, $this->version, $this->description, $this->changeLog, $this->licenseType, $this->licenseRes, $this->flags, $this->creation, $this->stage, $this->icon)->insert_id;

        // TODO update categories when entering stage RELEASE_STAGE_RESTRICTED
        // TODO update keywords when entering stage RELEASE_STAGE_TRUSTED
        $ID = $this->projectId;
        if(count($this->keywords) > 0) {
            MysqlUtils::query("DELETE FROM release_keywords WHERE projectId = ?", "i", $ID);
            MysqlUtils::insertBulk("INSERT INTO release_keywords (projectId, word) VALUES ", "is",
                $this->keywords, function (string $word) use ($ID) {
                    return [$ID, $word];
                });
        }
            MysqlUtils::query("DELETE FROM release_categories WHERE projectId = ?", "i", $ID);
            MysqlUtils::query("INSERT INTO release_categories (projectId, category, isMainCategory) VALUES (?, ?, ?)", "iii",
                $this->projectId, $this->mainCategory, 1);

            if (count($this->categories) > 0) {
            MysqlUtils::insertBulk("INSERT INTO release_categories (projectId, category) VALUES ", "ii",
                $this->categories, function (int $catId) use ($ID) {
                    return [$ID, $catId];
                });
            }

        if(count($this->dependencies) > 0) {
            MysqlUtils::insertBulk("INSERT INTO release_deps (releaseId, name, version, depRelId, isHard) VALUES ", "issii",
                $this->dependencies, function (PluginDependency $dep) use ($releaseId) {
                    return [$releaseId, $dep->name, $dep->version, $dep->dependencyReleaseId, $dep->isHard ? 1 : 0];
                });
        }

        if(count($this->permissions) > 0) {
            MysqlUtils::insertBulk("INSERT INTO release_perms (releaseId, type, val) VALUES ", "iis", $this->permissions,
                function (int $perm) use ($releaseId) {
                    return [$releaseId, PluginRelease::META_PERMISSION, $perm];
                });
        }

        MysqlUtils::insertBulk("INSERT INTO release_spoons (releaseId, since, till) VALUES ", "iss", $this->spoons,
            function (array $spoon) use ($releaseId) {
                return [$releaseId, $spoon[0], $spoon[1]];
            });

        if (count($this->requirements) > 0) {
        MysqlUtils::insertBulk("INSERT INTO release_reqr (releaseId, type, details, isRequire) VALUES ", "issi", $this->requirements,
            function (PluginRequirement $requirement) use ($releaseId) {
                return [$releaseId, $requirement->type, $requirement->details, $requirement->isRequire];
            });
        }

        $this->buildId = $releaseId;
        return (string) $this->version;
        } else {

            $result = MysqlUtils::query("UPDATE releases SET
            shortDesc = ?, artifact= ?, version = ?, description = ?, changelog = ?, license = ?, licenseRes = ?, flags = ?, creation = ?, state = ?, icon = ?
            WHERE releaseId = ?", str_replace(" ", "", " s         i        s          i           i         s          i        i       i        i     s     i"), $this->shortDesc, $this->artifact, $this->version, $this->description, $this->changeLog, $this->licenseType, $this->licenseRes, $this->flags, $this->creation, $this->stage, $this->icon, $this->existingReleaseId);

            // TODO update categories when entering stage RELEASE_STAGE_RESTRICTED
            // TODO update keywords when entering stage RELEASE_STAGE_TRUSTED
            // TODO update other metadata

        if(count($this->keywords) > 0) {
            $ID = $this->projectId;
            if(count($this->keywords) > 0) {
                MysqlUtils::query("DELETE FROM release_keywords WHERE projectId = ?", "i", $ID);
                MysqlUtils::insertBulk("INSERT INTO release_keywords (projectId, word) VALUES ", "is",
                $this->keywords, function (string $word) use ($ID) {
                return [$ID, $word];
                });
            }
        }

        MysqlUtils::query("DELETE FROM release_categories WHERE projectId = ?", "i", $this->projectId);
        MysqlUtils::query("INSERT INTO release_categories (projectId, category, isMainCategory) VALUES (?, ?, ?)", "iii",
            $this->projectId, $this->mainCategory, 1);
        if (count($this->categories) > 0){
        MysqlUtils::insertBulk("INSERT INTO release_categories (projectId, category) VALUES ", "ii",
            $this->categories, function (int $catId) use ($ID) {
            return [$ID, $catId];
            });
        }

        $releaseId = null;
        $releaseIdRows = MysqlUtils::query("SELECT releaseId FROM releases WHERE buildId = ? LIMIT 1", "i", $this->buildId);
         if (count($releaseIdRows) > 0) $releaseId = (int) $releaseIdRows[0]["releaseId"];

        if (isset($releaseId)) {
            MysqlUtils::query("DELETE FROM release_spoons WHERE releaseId = ?", "i", $releaseId);
            if (count($this->spoons) > 0) {
            MysqlUtils::insertBulk("INSERT INTO release_spoons (releaseId, since, till) VALUES ", "iss", $this->spoons,
            function (array $spoon) use ($releaseId) {
                    return [$releaseId, $spoon[0], $spoon[1]];
                });
            }
            MysqlUtils::query("DELETE FROM release_perms WHERE releaseId = ?", "i", $releaseId);
            if (count($this->permissions) > 0) {
            MysqlUtils::insertBulk("INSERT INTO release_perms (releaseId, type, val) VALUES ", "iis", $this->permissions,
                function (int $perm) use ($releaseId) {
                    return [$releaseId, PluginRelease::META_PERMISSION, $perm];
                });
            }
            MysqlUtils::query("DELETE FROM release_reqr WHERE releaseId = ?", "i", $releaseId);
            if (count($this->requirements) > 0) {
                    MysqlUtils::insertBulk("INSERT INTO release_reqr (releaseId, type, details, isRequire) VALUES ", "issi", $this->requirements, function (PluginRequirement $requirement) use ($releaseId) {
                        return [$releaseId, $requirement->type, $requirement->details, $requirement->isRequire];
                    });
                }
            MysqlUtils::query("DELETE FROM release_deps WHERE releaseId = ?", "i", $releaseId);
            if (count($this->dependencies) > 0) {
                    MysqlUtils::insertBulk("INSERT INTO release_deps (releaseId, name, version, depRelId, isHard) VALUES ", "issii", $this->dependencies, function ($dependency) use ($releaseId) {
                        return [$releaseId, $dependency->name, $dependency->version, (isset($dependency->depRelId) ? $dependency->depRelId : 0), $dependency->isHard];
                    });
                }
            }
            $this->buildId = $this->existingReleaseId;
            return (string) $this->version;
        }
    }

    public static function pluginPanel(IndexPluginThumbnail $plugin) {
        ?>
        <div class="plugin-entry">
            <div class="plugin-entry-block plugin-icon">
                <div class="plugin-image-wrapper">
                    <a href="<?= Poggit::getRootPath() ?>p/<?= htmlspecialchars($plugin->name) ?>/<?= $plugin->version ?>">
                        <img src="<?= $plugin->iconUrl ?? (Poggit::getRootPath() . "res/defaultPluginIcon2.png") ?>"
                             width="56" title="<?= htmlspecialchars($plugin->shortDesc) ?>"/>
                    </a>
                </div>
                <div class="smalldate-wrapper">
                    <span class="plugin-smalldate"><?= htmlspecialchars(date('d M Y', $plugin->creation)) ?></span>
                    <span class="plugin-smalldate"><?= $plugin->dlCount ?>
                        download<?= $plugin->dlCount != 1 ? "s" : "" ?></span>
                    <?php if (PluginRelease::getScores($plugin->projectId)["count"] > 0) { ?>
                    <span class="plugin-smalldate">score <?= PluginRelease::getScores($plugin->projectId)["average"] ?>/5 (<?= PluginRelease::getScores($plugin->projectId)["count"] ?>)</span>
                    <?php } ?>
                </div>
            </div>
            <div class="plugin-entry-block plugin-main">
                    <a href="<?= Poggit::getRootPath() ?>p/<?= htmlspecialchars($plugin->name) ?>/<?= $plugin->version ?>"><span
                            class="plugin-name"><?= htmlspecialchars($plugin->name) ?></span></a>
                    <span class="plugin-version">Version <?= htmlspecialchars($plugin->version) ?></span>
                    <span class="plugin-author">by <?php EmbedUtils::displayUser($plugin->author) ?></span>
            </div>
            <span class="plugin-state-<?= $plugin->state ?>"><?php echo htmlspecialchars(self::$STAGE_HUMAN[$plugin->state]) ?></span>
        </div>
        <?php
    }

    public static function getRecentPlugins(int $count): array {
        $result= [];
        $session = SessionUtils::getInstance();
        $plugins = MysqlUtils::query("SELECT
            r.releaseId, r.projectId AS projectId, r.name, r.version, rp.owner AS author, r.shortDesc,
            r.icon, r.state, r.flags, rp.private AS private, res.dlCount AS downloads, p.framework AS framework, UNIX_TIMESTAMP(r.creation) AS created
            FROM releases r
                INNER JOIN projects p ON p.projectId = r.projectId
                INNER JOIN repos rp ON rp.repoId = p.repoId
                INNER JOIN resources res ON res.resourceId = r.artifact
            ORDER BY state DESC LIMIT $count");
            $adminlevel = Poggit::getAdmlv($session->getLogin()["name"] ?? "");
        foreach($plugins as $plugin) {
            if ($session->getLogin()["name"] == $plugin["author"] || (int) $plugin["state"] >= PluginRelease::MIN_PUBLIC_RELSTAGE || (int) $plugin["state"] >= PluginRelease::RELEASE_STAGE_CHECKED && $session->isLoggedIn() || ($adminlevel >= Poggit::MODERATOR && (int) $plugin["state"] > PluginRelease::RELEASE_STAGE_DRAFT)){
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
                $thumbNail->isMine = ($session->getLogin()["name"] == $plugin["author"]) ? true : false;
                $thumbNail->dlCount = (int) $plugin["downloads"];
                $result[$thumbNail->id] = $thumbNail;
            }
        }
     return $result;
}

    public static function getPluginsByState(int $state, int $count = 30): array {
        $result= [];
        $session = SessionUtils::getInstance();
        $plugins = MysqlUtils::query("SELECT
            r.releaseId, r.projectId AS projectId, r.name, r.version, rp.owner AS author, r.shortDesc,
            r.icon, r.state, r.flags, rp.private AS private, res.dlCount AS downloads, p.framework AS framework, UNIX_TIMESTAMP(r.creation) AS created
            FROM releases r
                INNER JOIN projects p ON p.projectId = r.projectId
                INNER JOIN repos rp ON rp.repoId = p.repoId
                INNER JOIN resources res ON res.resourceId = r.artifact
                WHERE state <= $state AND state > 0
            ORDER BY state DESC LIMIT $count");
        $adminlevel = Poggit::getAdmlv($session->getLogin()["name"] ?? "");
        foreach($plugins as $plugin) {
            if ((int) $plugin["state"] >= PluginRelease::MIN_PUBLIC_RELSTAGE || ((int) $plugin["state"] >= PluginRelease::RELEASE_STAGE_CHECKED && $session->isLoggedIn()) || $adminlevel >= Poggit::MODERATOR ){
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
                $thumbNail->isMine = ($session->getLogin()["name"] == $plugin["author"]) ? true : false;
                $thumbNail->dlCount = (int) $plugin["downloads"];
                $result[$thumbNail->id] = $thumbNail;
            }
        }
        return $result;
    }

    /**
     * @param int $projectId
     * @return array
     */

    public static function getScores (int $projectId): array {
        $scores = MysqlUtils::query("SELECT SUM(rev.score) AS score, COUNT(*) as scorecount FROM release_reviews rev
        INNER JOIN releases rel ON rel.releaseId = rev.releaseId
        INNER JOIN projects p ON p.projectId = rel.projectId
        INNER JOIN repos r ON r.repoId = p.repoId
        WHERE rel.projectId = ? AND rel.state > 2 AND rev.user <> r.accessWith", "i", $projectId);
        return ["total" => $scores[0]["score"] ?? 0, "average" => round(($scores[0]["score"] ?? 0) / ((isset($scores[0]["scorecount"]) && $scores[0]["scorecount"] > 0) ? $scores[0]["scorecount"] : 1) , 1), "count" => $scores[0]["scorecount"] ?? 0];
    }

    /**
     * @param string $repoFullName
     * @param string $iconName
     * @param string $ref
     * @param string $token
     * @return object|string
     */
    public static function findIcon(string $repoFullName, string $iconName, string $ref, string $token) {
        try {
            $iconData = CurlUtils::ghApiGet("repos/$repoFullName/contents/$iconName?ref=$ref", $token);
            /** @var object|string $icon */
            $icon = new \stdClass();
            $icon->name = $iconName;
            $icon->url = $iconData->download_url;
            $icon->content = base64_decode($iconData->content);
            $iconSize = @getimagesizefromstring($icon->content);
            if($iconSize === false) {
                return "File at $iconName is of an unsupported format.";
            }
            $icon->mime = $iconSize["mime"];
            if(!in_array($icon->mime, ["image/jpeg", "image/gif", "image/png"])) {
                return "File at $iconName contains an image of unsupported format.";
            }
            if($iconSize[0] > 256 or $iconSize[1] > 256) {
                return "Icon found at $iconName must not exceed the dimensions 256x256 px.";
            }
            return $icon;
        } catch(GitHubAPIException $e) {
            return "Image cannot be found from $iconName";
        }
    }
}
