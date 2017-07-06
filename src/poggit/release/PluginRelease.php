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

use poggit\account\Session;
use poggit\Config;
use poggit\Mbd;
use poggit\Meta;
use poggit\release\index\IndexPluginThumbnail;
use poggit\resource\ResourceManager;
use poggit\resource\ResourceNotFoundException;
use poggit\timeline\NewPluginUpdateTimeLineEvent;
use poggit\utils\internet\Curl;
use poggit\utils\internet\GitHubAPIException;
use poggit\utils\internet\Mysql;
use poggit\utils\PocketMineApi;

class PluginRelease {
    const DEFAULT_CRITERIA = 0;
    public static $CRITERIA_HUMAN = [
        0 => "General",
        1 => "Code",
        2 => "Performance",
        3 => "Usefulness",
        4 => "Concept",
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

    const RELEASE_STATE_DRAFT = 0;
    const RELEASE_STATE_REJECTED = 1;
    const RELEASE_STATE_SUBMITTED = 2;
    const RELEASE_STATE_CHECKED = 3;
    const RELEASE_STATE_VOTED = 4;
    const RELEASE_STATE_APPROVED = 5;
    const RELEASE_STATE_FEATURED = 6;

    const AUTHOR_LEVEL_COLLABORATOR = 1; // a person who is in charge of a major part of the plugin (whether having written code directly or not, or just merely designing the code structure)
    const AUTHOR_LEVEL_CONTRIBUTOR = 2; // a person who added minor changes to the plugin's code, but is not officially in the team writing the plugin
    const AUTHOR_LEVEL_TRANSLATOR = 3; // a person who only contributes translations or other non-code changes for the plugin
    const AUTHOR_LEVEL_REQUESTER = 4; // a person who provides abstract ideas for the plugin

    public static $STATE_ID_TO_HUMAN = [
        PluginRelease::RELEASE_STATE_DRAFT => "Draft",
        PluginRelease::RELEASE_STATE_REJECTED => "Rejected",
        PluginRelease::RELEASE_STATE_SUBMITTED => "Submitted",
        PluginRelease::RELEASE_STATE_CHECKED => "Checked",
        PluginRelease::RELEASE_STATE_VOTED => "Voted",
        PluginRelease::RELEASE_STATE_APPROVED => "Approved",
        PluginRelease::RELEASE_STATE_FEATURED => "Featured"
    ];
    public static $STATE_SID_TO_ID = [
        "draft" => PluginRelease::RELEASE_STATE_DRAFT,
        "rejected" => PluginRelease::RELEASE_STATE_REJECTED,
        "submitted" => PluginRelease::RELEASE_STATE_SUBMITTED,
        "checked" => PluginRelease::RELEASE_STATE_CHECKED,
        "voted" => PluginRelease::RELEASE_STATE_VOTED,
        "approved" => PluginRelease::RELEASE_STATE_APPROVED,
        "featured" => PluginRelease::RELEASE_STATE_FEATURED
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
    /** @var int releaseId */
    public $releaseId;
    /** @var int parentReleaseId */
    public $parentReleaseId;
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
    public $state;
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
    /** @var array */
    public $assocs;
    /** @var int[] */
    public $permissions;
    /** @var PluginRequirement[] */
    public $requirements;
    /** @var string[][] spoon => [api0,api1] */
    public $spoons;
    /** @var int */
    public $existingState;
    /** @var string */
    public $existingVersionName;

    public static function validatePluginName(string $name, string &$error = null): bool {
        if(!preg_match(/** @lang RegExp */
            '%^[A-Za-z0-9_.\-]{2,}$%', $name)) {
            $error = "Plugin name must be at least two characters long, consisting of A-Z, a-z, 0-9, hyphen or underscore only";
            return false;
        }
        $rows = Mysql::query("SELECT COUNT(releases.name) AS dups FROM releases WHERE name = ? AND state >= ?", "si", $name, PluginRelease::RELEASE_STATE_CHECKED);
        $dups = (int) $rows[0]["dups"];
        if($dups > 0) {
            $error = "There are $dups other checked plugins with names starting with '$name'";
            return false;
        }
        $error = "Great name!";
        return true;
    }

    public static function validateVersionName(string $name, string &$error = null): bool {
        if(!preg_match('/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(-(0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*)(\.(0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*))*)?(\+[0-9a-zA-Z-]+(\.[0-9a-zA-Z-]+)*)?$/', $name)) {
            $error = "Plugin version numbers must contain at least 3 numbers in three groups separated by dots (.). The last group (PATCH) can also contain letters (a-Z), hyphens (-) and dots (.). Version numbers must follow the Semantic Versioning scheme.";
            return false;
        }
        // TODO check duplicate versions
        return true;
    }

    public static function fromSubmitJson(\stdClass $data): PluginRelease {
        $instance = new PluginRelease;

        if(!isset($data->buildId)) throw new SubmitException("Param 'buildId' missing");
        $rows = Mysql::query("SELECT p.repoId, p.path, b.projectId, b.branch, b.internal, b.resourceId, IFNULL(b.sha, b.branch) AS ref
            FROM builds b INNER JOIN projects p ON b.projectId = p.projectId WHERE b.buildId = ?", "i", $data->buildId);
        if(count($rows) === 0) throw new SubmitException("Param 'buildId' does not represent a valid build");
        $build = $rows[0];
        $buildArtifactId = (int) $build["resourceId"];
        unset($rows);
        $repoId = (int) $build["repoId"];
        $session = Session::getInstance();
        try {
            $repo = Curl::ghApiGet("repositories/$repoId", $token = $session->getAccessToken());
            if((!isset($repo->permissions) or !$repo->permissions->admin) && Meta::getUserAccess($session->getName()) < Meta::MODERATOR) throw new \Exception;
        } catch(\Exception $e) {
            throw new SubmitException("Admin access required for releasing plugins");
        }

        if(isset($data->iconName)) {
            $icon = PluginRelease::findIcon($repo->full_name, $build["path"] . $data->iconName, $build["ref"], $token);
            $instance->icon = is_object($icon) ? $icon->url : null;
        } else {
            $instance->icon = null;
        }

        $instance->buildId = (int) $data->buildId;
        $instance->projectId = (int) $build["projectId"];
        $releases = Mysql::query("SELECT buildId, releaseId, state, version, parent_releaseId FROM releases WHERE projectId = ? ORDER BY creation DESC", "i", $instance->projectId);
        if(!($newrelease = count($releases) === 0)) {
            foreach($releases as $key => $release) {
                if($release["buildId"] == $instance->buildId) {
                    $instance->existingReleaseId = (int) $release["releaseId"];
                    $instance->parentReleaseId = (int) $release["parent_releaseId"];
                    $instance->existingState = (int) $release["state"];
                    $instance->existingVersionName = $release["version"];
                    unset($releases[$key]);
                }
            }
            $prevVersions = array_map(function ($row) {
                return (($row["state"] > self::RELEASE_STATE_DRAFT) ? $row["version"] : null);
            }, $releases);
        }

        if(!isset($data->name)) throw new SubmitException("Param 'name' missing");
        $name = $data->name;
        if($newrelease && !PluginRelease::validatePluginName($name, $error)) throw new SubmitException("Invalid plugin name: $error");
        $instance->name = $name;

        if(!isset($data->shortDesc)) throw new SubmitException("Param 'shortDesc' missing");
        if(strlen($data->shortDesc) > Config::MAX_SHORT_DESC_LENGTH) throw new SubmitException("Param 'shortDesc' is too long");
        $instance->shortDesc = $data->shortDesc;

        if(!isset($data->version)) throw new SubmitException("Param 'version' missing");
        if(strlen($data->version) > Config::MAX_VERSION_LENGTH) throw new SubmitException("Version is too long");
        if(!$newrelease and in_array($data->version, $prevVersions)) throw new SubmitException("This version number has already been used for your plugin!");
        if(!PluginRelease::validateVersionName($data->version, $error)) throw new SubmitException("invalid plugin version: $error");
        $instance->version = $data->version;

        if(!isset($data->desc) or !($data->desc instanceof \stdClass)) throw new SubmitException("Param 'desc' missing or incorrect");
        $description = $data->desc;
        $descResRow = Mysql::query("SELECT description FROM releases WHERE buildId = ? LIMIT 1", "i", $data->buildId);
        if(count($descResRow) > 0) $descResId = (int) $descResRow[0]["description"];
        $descRsr = PluginRelease::storeArticle($descResId ?? null, $repo->full_name, $description, "description", "poggit.release.desc");
        $instance->description = $descRsr;

        if(!$newrelease && $instance->existingVersionName != $data->version) {
            if(!$data->asDraft && !isset($data->changeLog)) throw new SubmitException("Param 'changeLog' missing");
            if(!$data->asDraft && $data->changeLog instanceof \stdClass) {
                $changeLog = $data->changeLog;
                $clResId = null;
                $clResRow = Mysql::query("SELECT changelog FROM releases WHERE buildId = ? LIMIT 1", "i", $data->buildId);
                if(count($clResRow) > 0) $clResId = (int) $clResRow[0]["changelog"] !== ResourceManager::NULL_RESOURCE ? (int) $clResRow[0]["changelog"] : null;
                $clRsr = PluginRelease::storeArticle($clResId, $repo->full_name, $changeLog, "changelog", "poggit.release.chlog");
                $instance->changeLog = $clRsr;
            } else $instance->changeLog = ResourceManager::NULL_RESOURCE;
        } else $instance->changeLog = ResourceManager::NULL_RESOURCE;

        if(!isset($data->license) or !($data->license instanceof \stdClass)) throw new SubmitException("Param 'license' missing or incorrect");
        $license = $data->license;
        $type = strtolower($license->type);
        if($type === "custom") {
            $license->type = "txt";
            if(!isset($license->text) || strlen($license->text) > Config::MAX_LICENSE_LENGTH) throw new SubmitException("Custom licence text is empty or invalid");
            $licResId = null;
            $licenseResRow = Mysql::query("SELECT licenseRes FROM releases WHERE buildId = ? LIMIT 1", "i", $data->buildId);
            if(count($licenseResRow) > 0) $licResId = (int) $licenseResRow[0]["licenseRes"];
            $licRsr = PluginRelease::storeArticle($licResId, $repo->full_name, $license, "custom license", "poggit.release.license");
            $instance->licenseText = $license->text;
            $instance->licenseType = "custom";
            $instance->licenseRes = $licRsr;
        } elseif($type === "none") {
            $instance->licenseType = "none";
        } else {
            $licenseData = Curl::ghApiGet("licenses", $token, ["Accept: application/vnd.github.drax-preview+json"]);
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
            if($cat != $cats->major) $instance->categories[] = $cat;
        }

        if(!isset($data->keywords)) throw new SubmitException("Param 'keywords' missing");
        $data->keywords = array_values(array_unique(array_filter($data->keywords, "string_not_empty")));
        $keywords = [];
        if(!$data->asDraft && count($data->keywords) === 0) throw new SubmitException("Please enter at least one keyword so that others can search for your plugin!");
        if(count($data->keywords) > Config::MAX_KEYWORD_COUNT) $data->keywords = array_slice($data->keywords, 0, Config::MAX_KEYWORD_COUNT);
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
        $instance->assocs = $instance->parentReleaseId === 0 ? $data->assocs : [];
        $instance->dependencies = [];
        foreach($data->deps ?? [] as $i => $dep) {
            if(!isset($dep->releaseId, $dep->softness)) throw new SubmitException("Param deps[$i] is incorrect");
            $rows = Mysql::query("SELECT releaseId, name, version FROM releases WHERE releaseId = ?", "i", $dep->releaseId);
            if(count($rows) === 0) throw new SubmitException("Param deps[$i] declares invalid dependency");
            $depName = $rows[0]["name"];
            $depVersion = $rows[0]["version"];
            $depRelId = $rows[0]["releaseId"];
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

        $newstate = PluginRelease::RELEASE_STATE_SUBMITTED;
        if($data->asDraft) {
            $newstate = PluginRelease::RELEASE_STATE_DRAFT;
        } else {
            if($instance->existingState && $instance->existingState > PluginRelease::RELEASE_STATE_SUBMITTED) {
                $newstate = $instance->existingState;
            }
        }
        $instance->state = $newstate;

        // prepare artifact at last step to save memory
        $artifact = PluginRelease::prepareArtifactFromResource($buildArtifactId, $instance->version);
        $instance->artifact = $artifact;
        return $instance;
    }

    private static function storeArticle(int $oldRsrId = null, string $ctx, \stdClass $data, string $field, string $src): int {
        $type = $data->type ?? "md";
        $value = $data->text ?? "";
        $newRsrId = null;
        if($field !== null and strlen($value) < 10) throw new SubmitException("Please write a proper $field for your plugin! Your description is far too short!");

        if($type === "txt") {
            $file = $oldRsrId ? ResourceManager::getInstance()->pathTo($oldRsrId, "txt") : ResourceManager::getInstance()->createResource("txt", "text/plain", [], $newRsrId, 315360000, $src);
            file_put_contents($file, htmlspecialchars($value));
            if($oldRsrId !== null) {
                Mysql::query("UPDATE resources SET type = ?, mimeType = ? WHERE resourceId = ?", "ssi", "txt", "text/plain", $oldRsrId);
            }
        } elseif($type === "md") {
            $data = Curl::ghApiPost("markdown", ["text" => $value, "mode" => "markdown", "context" => $ctx],
                Session::getInstance()->getAccessToken(), true);
            $file = $oldRsrId ? ResourceManager::getInstance()->pathTo($oldRsrId, "html") : ResourceManager::getInstance()->createResource("html", "text/html", [], $newRsrId, 315360000, $src);
            file_put_contents($file, $data);
            if($oldRsrId !== null) {
                Mysql::query("UPDATE resources SET type = ?, mimeType = ? WHERE resourceId = ?", "ssi", "html", "text/html", $oldRsrId);
            }
            $relMdFile = ResourceManager::getInstance()->createResource("md", "text/markdown", [], $relMd, 315360000, $src . ".relmd");
            file_put_contents($relMdFile, $value);
            Mysql::query("UPDATE resources SET relMd = ? WHERE resourceId = ?", "ii", $relMd, $newRsrId ?? $oldRsrId);
        } else {
            throw new SubmitException("Unknown type '$type'");
        }
        return $newRsrId ?? $oldRsrId;
    }

    private static function searchApiByString(string $api): int {
        $result = array_search($api, array_keys(PocketMineApi::$VERSIONS));
        if($result === false) throw new SubmitException("Unknown API version $api");
        return $result;
    }

    private static function prepareArtifactFromResource(int $oldId, string $version): int {
        $file = ResourceManager::getInstance()->createResource("phar", "application/octet-stream", [], $newId, 315360000, "poggit.release.artifact");
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
        if(!isset($this->existingReleaseId)) {
            $releaseId = Mysql::query("INSERT INTO releases 
            (name, shortDesc, artifact, projectId, buildId, version, description, changelog, license, licenseRes, flags, creation, state, icon) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", str_replace(" ", "",
                " s        s         i          i         i        s          i           i         s          i        i       i        i     s  "),
                $this->name, $this->shortDesc, $this->artifact, $this->projectId, $this->buildId, $this->version, $this->description, $this->changeLog, $this->licenseType, $this->licenseRes, $this->flags, $this->creation, $this->state, $this->icon)->insert_id;

            $projectId = $this->projectId;
            if(count($this->keywords) > 0) {
                Mysql::query("DELETE FROM release_keywords WHERE projectId = ?", "i", $projectId);
                Mysql::insertBulk("INSERT INTO release_keywords (projectId, word) VALUES ", "is",
                    $this->keywords, function (string $word) use ($projectId) {
                        return [$projectId, $word];
                    });
            }
            Mysql::query("DELETE FROM release_categories WHERE projectId = ?", "i", $projectId);
            Mysql::query("INSERT INTO release_categories (projectId, category, isMainCategory) VALUES (?, ?, ?)", "iii",
                $this->projectId, $this->mainCategory, 1);

            if(count($this->categories) > 0) {
                Mysql::insertBulk("INSERT INTO release_categories (projectId, category, isMainCategory) VALUES ", "iii",
                    $this->categories, function (int $catId) use ($projectId) {
                        return [$projectId, $catId, 0];
                    });
            }

            if(count($this->assocs) > 0) {
                $user = Session::getInstance()->getName();
                foreach($this->assocs as $assoc) {
                    Mysql::query("UPDATE releases r
                                INNER JOIN projects p ON r.projectId = p.projectId
                                INNER JOIN repos rp ON p.repoId = rp.repoId 
                                SET r.parent_releaseId = ? WHERE r.releaseId = ? AND rp.owner = ?", "iis",
                        $releaseId, $assoc->releaseId, $user);
                }
            }

            if(count($this->dependencies) > 0) {
                Mysql::insertBulk("INSERT INTO release_deps (releaseId, name, version, depRelId, isHard) VALUES ", "issii",
                    $this->dependencies, function (PluginDependency $dep) use ($releaseId) {
                        return [$releaseId, $dep->name, $dep->version, $dep->dependencyReleaseId, $dep->isHard ? 1 : 0];
                    });
            }

            if(count($this->permissions) > 0) {
                Mysql::insertBulk("INSERT INTO release_perms (releaseId, val) VALUES ", "iis", $this->permissions,
                    function (int $perm) use ($releaseId) {
                        return [$releaseId, $perm];
                    });
            }

            Mysql::insertBulk("INSERT INTO release_spoons (releaseId, since, till) VALUES ", "iss", $this->spoons,
                function (array $spoon) use ($releaseId) {
                    return [$releaseId, $spoon[0], $spoon[1]];
                });

            if(count($this->requirements) > 0) {
                Mysql::insertBulk("INSERT INTO release_reqr (releaseId, type, details, isRequire) VALUES ", "issi", $this->requirements,
                    function (PluginRequirement $requirement) use ($releaseId) {
                        return [$releaseId, $requirement->type, $requirement->details, $requirement->isRequire];
                    });
            }

            $this->releaseId = $releaseId;
            $this->writeEvent();
            return (string) $this->version;
        } else {
            Mysql::query("UPDATE releases SET 
                shortDesc = ?, version = ?, description = ?, changelog = ?, license = ?, licenseRes = ?, flags = ?, creation = ?, state = ?, icon = ? WHERE releaseId = ?", str_replace(" ", "",
                "           s            s                i              i            s               i          i             i          i         s                   i"),
                $this->shortDesc, $this->version, $this->description, $this->changeLog, $this->licenseType, $this->licenseRes, $this->flags, $this->creation, $this->state, $this->icon, $this->existingReleaseId);

            $projectId = $this->projectId;
            if(count($this->keywords) > 0) {
                if(count($this->keywords) > 0) {
                    Mysql::query("DELETE FROM release_keywords WHERE projectId = ?", "i", $projectId);
                    Mysql::insertBulk("INSERT INTO release_keywords (projectId, word) VALUES ", "is",
                        $this->keywords, function (string $word) use ($projectId) {
                            return [$projectId, $word];
                        });
                }
            }

            Mysql::query("DELETE FROM release_categories WHERE projectId = ?", "i", $this->projectId);
            Mysql::query("INSERT INTO release_categories (projectId, category, isMainCategory) VALUES (?, ?, ?)", "iii",
                $this->projectId, $this->mainCategory, 1);
            if(count($this->categories) > 0) {
                Mysql::insertBulk("INSERT INTO release_categories (projectId, category) VALUES ", "ii",
                    $this->categories, function (int $catId) use ($projectId) {
                        return [$projectId, $catId];
                    });
            }

            $releaseId = null;
            $releaseIdRows = Mysql::query("SELECT releaseId FROM releases WHERE buildId = ? LIMIT 1", "i", $this->buildId);
            if(count($releaseIdRows) > 0) $releaseId = (int) $releaseIdRows[0]["releaseId"];

            if(isset($releaseId)) {
                Mysql::query("DELETE FROM release_spoons WHERE releaseId = ?", "i", $releaseId);
                if(count($this->spoons) > 0) {
                    Mysql::insertBulk("INSERT INTO release_spoons (releaseId, since, till) VALUES ", "iss", $this->spoons,
                        function (array $spoon) use ($releaseId) {
                            return [$releaseId, $spoon[0], $spoon[1]];
                        });
                }
                Mysql::query("DELETE FROM release_perms WHERE releaseId = ?", "i", $releaseId);
                if(count($this->permissions) > 0) {
                    Mysql::insertBulk("INSERT INTO release_perms (releaseId, type, val) VALUES ", "iis", $this->permissions,
                        function (int $perm) use ($releaseId) {
                            return [$releaseId, PluginRelease::META_PERMISSION, $perm];
                        });
                }
                Mysql::query("DELETE FROM release_reqr WHERE releaseId = ?", "i", $releaseId);
                if(count($this->requirements) > 0) {
                    Mysql::insertBulk("INSERT INTO release_reqr (releaseId, type, details, isRequire) VALUES ", "issi", $this->requirements, function (PluginRequirement $requirement) use ($releaseId) {
                        return [$releaseId, $requirement->type, $requirement->details, $requirement->isRequire];
                    });
                }
                Mysql::query("UPDATE releases SET parent_releaseId = NULL WHERE parent_releaseId = ?", "i", $releaseId);
                if(count($this->assocs) > 0) {
                    $user = Session::getInstance()->getName();
                    foreach($this->assocs as $assoc) {
                        Mysql::query("UPDATE releases r
                                INNER JOIN projects p ON r.projectId = p.projectId
                                INNER JOIN repos rp ON p.repoId = rp.repoId 
                                SET r.parent_releaseId = ? WHERE r.releaseId = ? AND rp.owner = ?", "iis",
                            $releaseId, $assoc->releaseId, $user);
                    }
                }

                Mysql::query("DELETE FROM release_deps WHERE releaseId = ?", "i", $releaseId);
                if(count($this->dependencies) > 0) {
                    Mysql::insertBulk("INSERT INTO release_deps (releaseId, name, version, depRelId, isHard) VALUES ", "issii", $this->dependencies, function (PluginDependency $dep) use ($releaseId) {
                        return [$releaseId, $dep->name, $dep->version, $dep->dependencyReleaseId, $dep->isHard ? 1 : 0];
                    });
                }
            }
            $this->releaseId = $releaseId;
            $this->writeEvent();
            return (string) $this->version;
        }
    }

    public function writeEvent() {
        $event = new NewPluginUpdateTimeLineEvent();
        $event->releaseId = $this->releaseId;
        $event->oldState = $this->existingState;
        $event->newState = $this->state;
        $event->changedBy = Session::getInstance()->getName();
        $event->dispatch();
    }

    public static function pluginPanel(IndexPluginThumbnail $plugin) {
        $scores = PluginRelease::getScores($plugin->projectId);
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
        $adminlevel = Meta::getUserAccess($session->getName());
        foreach($plugins as $plugin) {
            if($session->getName() === $plugin["author"] || (int) $plugin["state"] >= Config::MIN_PUBLIC_RELEASE_STATE || (int) $plugin["state"] >= PluginRelease::RELEASE_STATE_CHECKED && $session->isLoggedIn() || ($adminlevel >= Meta::MODERATOR && (int) $plugin["state"] > PluginRelease::RELEASE_STATE_DRAFT)) {
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
        $adminlevel = Meta::getUserAccess($session->getName());
        foreach($plugins as $plugin) {
            if((int) $plugin["state"] >= Config::MIN_PUBLIC_RELEASE_STATE || ((int) $plugin["state"] >= PluginRelease::RELEASE_STATE_CHECKED && $session->isLoggedIn()) || $adminlevel >= Meta::MODERATOR) {
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

    /**
     * @param int $projectId
     * @return array
     */
    public static function getScores(int $projectId): array {
        $scores = Mysql::query("SELECT SUM(rev.score) AS score, COUNT(*) AS scorecount FROM release_reviews rev
        INNER JOIN releases rel ON rel.releaseId = rev.releaseId
        INNER JOIN projects p ON p.projectId = rel.projectId
        INNER JOIN repos r ON r.repoId = p.repoId
        WHERE rel.projectId = ? AND rel.state > 1 AND rev.user <> r.accessWith", "i", $projectId);

        $totaldl = Mysql::query("SELECT SUM(res.dlCount) AS totaldl FROM resources res
		INNER JOIN releases rel ON rel.projectId = ?
        WHERE res.resourceId = rel.artifact", "i", $projectId);
        return ["total" => $scores[0]["score"] ?? 0, "average" => round(($scores[0]["score"] ?? 0) / ((isset($scores[0]["scorecount"]) && $scores[0]["scorecount"] > 0) ? $scores[0]["scorecount"] : 1), 1), "count" => $scores[0]["scorecount"] ?? 0, "totaldl" => $totaldl[0]["totaldl"] ?? 0];
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
            $iconData = Curl::ghApiGet("repos/$repoFullName/contents/$iconName?ref=$ref", $token);
            /** @var object|string $icon */
            $icon = new \stdClass();
            $icon->name = $iconName;
            $icon->url = $iconData->download_url;
            $icon->content = base64_decode($iconData->content);
            /** @noinspection PhpParamsInspection */
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
