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

namespace poggit\release\submit;

use poggit\account\Session;
use poggit\Config;
use poggit\Meta;
use poggit\release\PluginRequirement;
use poggit\release\Release;
use poggit\release\SubmitException;
use poggit\resource\ResourceManager;
use poggit\utils\internet\Curl;
use poggit\utils\internet\Mysql;
use poggit\utils\lang\Lang;
use stdClass;

/**
 * Files in this class are set to false rather than null to show that they have been initialized.
 */
class PluginSubmission {
    /** @var string "submit"|"draft" */
    public $action;

    /** @var stdClass */
    public $repoInfo;
    /** @var stdClass */
    public $buildInfo;
    /** @var stdClass */
    public $refRelease;
    /** @var stdClass */
    public $lastValidVersion;
    /** @var string */
    public $mode;
    /** @var string|false Icon URL */
    public $icon;
    /** @var int */
    public $time;

    /** @var string */
    public $name;
    /** @var string */
    public $shortDesc;
    /** @var bool */
    public $official = false;
    /** @var int|stdClass {type: string, text: string} */
    public $description;
    /** @var string */
    public $version;
    /** @var bool */
    public $preRelease;
    /** @var bool */
    public $outdated = false;
    /** @var int|stdClass|bool {type: string, text: string} */
    public $changelog = false;
    /** @var int */
    public $majorCategory;
    /** @var int[] */
    public $minorCategories;
    /** @var string|string[] */
    public $keywords;
    /** @var stdClass[] [{name: string, version: string, depRelId: int, required: bool}] */
    public $deps;
    /** @var stdClass[] [{type: int, details: string, isRequire: bool}] */
    public $requires;
    /** @var array[] [[string, string]] */
    public $spoons;
    /** @var stdClass {releaseId: int, name: string, version: string} */
    public $assocParent = false;
    /** @var int[] */
    public $assocChildrenUpdates = [];
    /** @var stdClass {type: string, custom: string|null} */
    public $license;
    /** @var int[] */
    public $perms;
    /** @var stdClass[] [{uid: int, name: string, level: int}] */
    public $authors;

    public $artifact = 0;

//    TODO: public $artifactCompressed;


    public function validate() {
        try {
            Lang::nonNullFields($this);
        } catch(\InvalidArgumentException $e) {
            throw new SubmitException($e->getMessage());
        }
        $this->fixTypes();
        if($this->action !== "submit" && $this->action !== "draft") throw new SubmitException("Invalid action");
        if($this->action === "submit") {
            $this->strictValidate();
        }
    }

    private function fixTypes() {
        $this->name = (string) $this->name;
        $this->shortDesc = (string) $this->shortDesc;
        $this->official = (bool) $this->official;
        $this->description->type = (string) $this->description->type;
        $this->description->text = (string) $this->description->text;
        $this->version = (string) $this->version;
        $this->preRelease = (bool) $this->preRelease;
        $this->outdated = (bool) $this->outdated;
        if($this->changelog !== false) {
            $this->changelog->type = (string) $this->changelog->type;
            $this->changelog->text = (string) $this->changelog->text;
        }
        $this->majorCategory = (int) $this->majorCategory;
        $this->minorCategories = array_map("intval", array_unique($this->minorCategories));
        $this->keywords = explode(" ", $this->keywords);
        $this->assocChildrenUpdates = array_map("intval", array_unique($this->assocChildrenUpdates));
        $this->perms = array_map("intval", array_unique($this->perms));
    }

    private function strictValidate() {
        if($this->mode === SubmitModule::MODE_SUBMIT) {
            if(!Release::validateName($this->name, $error)) throw new SubmitException($error);
        }
        $adminLevel = Meta::getAdmlv();
        if(strlen($this->shortDesc) < Config::MIN_SHORT_DESC_LENGTH || strlen($this->shortDesc) > Config::MAX_SHORT_DESC_LENGTH) {
            throw new SubmitException("length(shortDesc) not in [" . Config::MIN_SHORT_DESC_LENGTH . "," . Config::MAX_SHORT_DESC_LENGTH . "]");
        }
        if($adminLevel <= Meta::ADMLV_REVIEWER) $this->official = false;
        if(!in_array($this->description->type, ["txt", "sm", "gfm"])) {
            throw new SubmitException("Invalid description.type");
        }
        if(strlen($this->description->text) < Config::MIN_DESCRIPTION_LENGTH) {
            throw new SubmitException("length(description.text) < " . Config::MIN_DESCRIPTION_LENGTH);
        }
        if(!Release::validateVersion($this->buildInfo->projectId, $this->version, $error)) {
            throw new SubmitException($error);
        }
        if($this->mode !== SubmitModule::MODE_EDIT && $this->outdated) throw new SubmitException("Why would you submit an outdated version?");
        if($this->lastValidVersion !== null) {
            if(!in_array($this->changelog->type, ["txt", "sm", "gfm"])) {
                throw new SubmitException("Invalid changelog.type");
            }
            if(strlen($this->changelog->text) < Config::MIN_CHANGELOG_LENGTH) {
                throw new SubmitException("length(changelog.txt) < " . Config::MIN_CHANGELOG_LENGTH);
            }
        } else {
            $this->changelog = false;
        }
        if(($index = array_search($this->majorCategory, $this->minorCategories)) !== false) {
            unset($this->minorCategories[$index]);
            $this->minorCategories = array_values($this->minorCategories);
        }
        if(count($this->keywords) > Config::MAX_KEYWORD_COUNT) {
            throw new SubmitException("count(keywords) > " . Config::MAX_KEYWORD_COUNT);
        }
        foreach($this->keywords as $keyword) {
            if(strlen($keyword) > Config::MAX_KEYWORD_LENGTH) {
                throw new SubmitException("length(keyword '$keyword') > " . Config::MAX_KEYWORD_LENGTH);
            }
            if(strpos($keyword, " ") !== false) {
                throw new SubmitException ("keyword '$keyword' contains space");
            }
        }
        if(max(array_map("strlen", $this->keywords)) > Config::MAX_KEYWORD_LENGTH) {
            throw new SubmitException("length(keywords[n]) > " . Config::MAX_KEYWORD_LENGTH);
        }
        /*deps*/
        $deps = [];
        foreach($this->deps as $dep) {
            $deps[$dep->depRelId] = (object) ["required" => $dep->required, "depRelId" => $dep->depRelId];
        }
        $this->deps = [];
        foreach(Mysql::arrayQuery("SELECT projectId, releaseId, name, version, state FROM releases WHERE releaseId IN (%s)",
            ["i", array_keys($deps)]) as $row) {
            if(Release::STATE_REJECTED >= (int) $row["state"]) throw new SubmitException("release({$row["releaseId"]}).state <= REJECTED");
            $projectId = (int) $row["projectId"];
            if(isset($this->deps[$projectId])) {
                throw new SubmitException(sprintf("Two versions of %s (v%s, v%s) passed as dependencies", $row["name"], $row["version"], $this->deps[$projectId]));
            }
            $releaseId = (int) $row["releaseId"];
            $this->deps[$projectId] = $dep = $deps[$releaseId];
            $dep->name = $row["name"];
            $dep->version = $row["version"];
            unset($deps[$releaseId]);
        }
        if(count($deps) > 0) throw new SubmitException("release(" . array_keys($deps)[0] . ") does not exist");
        foreach($this->requires as $require) {
            if(!isset($require->type, $require->details, $require->isRequire)) {
                throw new SubmitException("Malformed requirement " . json_encode($require));
            }
            if(!isset(PluginRequirement::$CONST_TO_DETAILS[$require->type])) {
                throw new SubmitException("Unknown requirement type $require->type");
            }
        }
        Meta::getLog()->jd($this->spoons);
        Meta::getLog()->jd(SubmitModule::rangesToApis($this->spoons));
        $this->spoons = SubmitModule::apisToRanges(SubmitModule::rangesToApis($this->spoons)); // validation and cleaning
        Meta::getLog()->jd($this->spoons);
        if(count($this->spoons) === 0) throw new SubmitException("Missing supported API versions");
        if($this->assocParent !== false) {
            $releaseId = $this->assocParent->releaseId;
            $rows = Mysql::query("SELECT releases.name, releases.version, repos.repoId, releases.state, projects.projectId FROM releases
                    INNER JOIN projects ON releases.projectId = projects.projectId
                    INNER JOIN repos ON projects.repoId = repos.repoId
                WHERE releases.releaseId = ?", "i", $releaseId);
            if(count($rows) === 0) throw new SubmitException("release($releaseId) does not exist");
            $parentRepo = Curl::ghApiGet("repositories/" . (int) $rows[0]["repoId"], Session::getInstance()->getAccessToken());
            if($parentRepo->owner->id !== $this->repoInfo->owner->id) {
                throw new SubmitException("Only plugins of the same repo owner can be associated together");
            }
            $this->assocParent = (object) [
                "releaseId" => $releaseId,
                "name" => $rows[0]["name"],
                "version" => $rows[0]["version"]
            ];
            if(isset($this->deps[(int) $rows[0]["projectId"]])) unset($this->deps[(int) $rows[0]["projectId"]]); // exclude associate from deps
        }
        // not gonna validate assocChildrenUpdates here
        if($this->license->type !== "custom" && $this->license->type !== "none") {
            // TODO validate license name from GitHub
        }
        if($this->repoInfo->owner->type === "Organization" and count($this->authors) === 0) {
            throw new SubmitException("At least one producer must be provided for organization-owned plugins");
        }
        $this->checkAuthorNames();
    }

    private function checkAuthorNames() {
        $names = [];
        foreach($this->authors as $author) {
            if(!isset(Release::$AUTHOR_TO_HUMAN[$author->level])) throw new SubmitException("Invalid author level $author->level");
            $names["u" . $author->uid] = $author->name;
        }
        $query = "query(";
        foreach($names as $k => $name) {
            $query .= "\${$k}:String!";
        }
        $query .= "){";
        foreach($names as $k => $name) {
            $query .= "{$k}: user(login: \${$k}){ uid:databaseId }";
        }
        $query .= "}";
        foreach(Curl::ghApiPost("graphql", [
            "query" => $query,
            "variables" => $names
        ], Session::getInstance()->getAccessToken())->data as $k => $user) {
            if($user === null) throw new SubmitException("No GitHub user called {$names[$k]}");
            $uid = (int) substr($k, 1);
            if($uid !== (int) $user->uid) throw new SubmitException("user(id:$uid) <> user(name:{$names[$k]})");
        }
    }

    public function resourcify() {
        $this->description = ResourceManager::getInstance()->storeArticle($this->description->type, $this->description->text, $this->repoInfo->full_name);
        if($this->changelog !== false) {
            $this->changelog = ResourceManager::getInstance()->storeArticle($this->changelog->type, $this->changelog->text, $this->repoInfo->full_name);
        }
        if($this->license->type === "custom") {
            $this->license->custom = ResourceManager::getInstance()->storeArticle("txt", $this->license->custom);
        } else {
            $this->license->custom = null;
        }
    }

    public function processArtifact() {
        $artifactPath = ResourceManager::getInstance()->createResource("phar", "application/octet-stream", [], $artifact);
        copy($this->buildInfo->devBuildRsrPath, $artifactPath);
        $pharUrl = "phar://" . str_replace(DIRECTORY_SEPARATOR, "/", realpath($artifactPath)) . "/";
        $py = yaml_parse(file_get_contents($pharUrl . "plugin.yml"));
        $py["name"] = $this->name;
        $py["version"] = $this->version;
        $py["api"] = SubmitModule::rangesToApis($this->spoons);
        file_put_contents($pharUrl . "plugin.yml", yaml_emit($py));
        if(!is_file($pharUrl . "LICENSE")) {
            // TODO insert license here
        }
        $phar = new \Phar($artifactPath);
        $phar->setMetadata(array_merge($phar->getMetadata(), [
            "poggitRelease" => [
                "date" => time(),
                "official" => $this->official,
                "preRelease" => $this->preRelease,
                "outdated" => $this->outdated,
                "majorCategory" => Release::$CATEGORIES[$this->majorCategory],
                "minorCategories" => array_map(function ($cat) {
                    return Release::$CATEGORIES[$cat];
                }, $this->minorCategories),
                "keywords" => $this->keywords,
                "requires" => $this->requires,
                "license" => $this->license->type,
                "perms" => array_map(function ($perm) {
                    return Release::$PERMISSIONS[$perm];
                }, $this->perms),
                "producers" => $this->authors,
                "link" => Meta::getSecret("meta.extPath") . "r/$this->artifact/$this->name.phar"
            ]
        ]));
        $this->artifact = $artifact;
        // TODO add compressed artifact here
    }

    public function save(): int {
        if($this->mode === SubmitModule::MODE_SUBMIT || $this->mode === SubmitModule::MODE_UPDATE) {
            $targetState = $this->action === "submit" ? Release::STATE_SUBMITTED : Release::STATE_DRAFT;
        } elseif($this->action === "submit") {
            $targetState = null;
        } else throw new SubmitException("Cannot edit published release to draft");

        $releaseId = Mysql::query("INSERT INTO releases
            (name, shortDesc, artifact, projectId, buildId, version, description, icon, changelog, license, licenseRes, flags, state, parent_releaseId)
     VALUES (?   , ?        , ?       , ?        , ?      , ?      , ?          , ?   , ?        , ?      , ?         , ?    , ?    , ?)", str_replace(["\n", " "], "", "
             s     s          i         i          i        s        i            s     i          s        i           i      i      i"),
            $this->name, $this->shortDesc, $this->artifact, $this->buildInfo->projectId, $this->buildInfo->buildId,
            $this->version, $this->description, $this->icon ?: null, $this->changelog, $this->license->type, $this->license->custom,
            ($this->preRelease ? Release::FLAG_PRE_RELEASE : 0) | ($this->outdated ? Release::FLAG_OUTDATED : 0) | ($this->official ? Release::FLAG_OFFICIAL : 0),
            $targetState, $this->assocParent === false ? null : $this->assocParent->releaseId)->insert_id;

        Mysql::query("DELETE FROM release_categories WHERE projectId = ?", "i", $this->buildInfo->projectId); // categories
        $first = true;
        Mysql::insertBulk("INSERT INTO release_categories (projectId, category, isMainCategory) VALUES",
            "iii", array_merge([$this->majorCategory], $this->minorCategories), function ($cat) use (&$first) {
                $ret = [$this->buildInfo->projectId, $cat, $first ? 1 : 0];
                $first = false;
                return $ret;
            });

        Mysql::query("DELETE FROM release_keywords WHERE projectId = ?", "i", $this->buildInfo->projectId); // keywords
        Mysql::insertBulk("INSERT INTO release_keywords (projectId, word) VALUES", "is", $this->keywords, function ($keyword) {
            return [$this->buildInfo->projectId, $keyword];
        });

        Mysql::insertBulk("INSERT INTO release_deps (releaseId, name, version, depRelId, isHard) VALUES", "issii", $this->deps, function ($dep) use ($releaseId) {
            return [$releaseId, $dep->name, $dep->version, $dep->depRelId, $dep->required];
        }); // deps
        Mysql::insertBulk("INSERT INTO release_reqr (releaseId, type, details, isRequire) VALUES", "iisi", $this->requires, function ($require) use ($releaseId) {
            return [$releaseId, $require->type, $require->details, $require->isRequire];
        }); // requires
        Mysql::insertBulk("INSERT INTO release_spoons (releaseId, since, till) VALUES", "iss", $this->spoons, function ($spoon) use ($releaseId) {
            return [$releaseId, $spoon[0], $spoon[1]];
        }); // spoons
        Mysql::insertBulk("INSERT INTO release_perms (releaseId, val) VALUES", "ii", $this->perms, function ($perm) use ($releaseId) {
            return [$releaseId, $perm];
        }); // perms

        Mysql::query("DELETE FROM release_authors WHERE projectId = ?", "i", $this->buildInfo->projectId); // authors
        Mysql::insertBulk("INSERT INTO release_authors (projectId, uid, name, level) VALUES", "iisi", $this->authors, function ($author) {
            return [$this->buildInfo->projectId, $author->uid, $author->name, $author->level];
        });

        if(count($this->assocChildrenUpdates) > 0) {
            Mysql::arrayQuery("UPDATE releases SET parent_releaseId = %s WHERE releaseId IN (%s)",
                ["i", $releaseId], ["i", $this->assocChildrenUpdates]);
        } // assocChildren

        return $releaseId;
    }
}
