<?php

/*
 * Poggit
 *
 * Copyright (C) 2016-2018 Poggit
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

use InvalidArgumentException;
use Phar;
use poggit\account\Session;
use poggit\Config;
use poggit\Meta;
use poggit\release\PluginRequirement;
use poggit\release\Release;
use poggit\release\SubmitException;
use poggit\resource\ResourceManager;
use poggit\utils\internet\Curl;
use poggit\utils\internet\GitHub;
use poggit\utils\internet\Mysql;
use poggit\utils\lang\Lang;
use stdClass;
use function array_keys;
use function array_map;
use function array_merge;
use function array_search;
use function array_unique;
use function array_values;
use function assert;
use function copy;
use function count;
use function explode;
use function file_get_contents;
use function file_put_contents;
use function filesize;
use function in_array;
use function is_file;
use function json_encode;
use function max;
use function realpath;
use function sprintf;
use function str_replace;
use function strlen;
use function strpos;
use function substr;
use function time;
use const DIRECTORY_SEPARATOR;

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
    /** @var stdClass|false */
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
    public $official;
    /** @var int|stdClass {type: string, text: string} */
    public $description;
    /** @var string */
    public $version;
    /** @var bool */
    public $preRelease;
    /** @var bool */
    public $outdated;
    /** @var bool */
    public $abandoned;
    /** @var int|stdClass|bool {type: string, text: string} */
    public $changelog;
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
        } catch(InvalidArgumentException $e) {
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
        $this->abandoned = (bool) $this->abandoned;
        if($this->changelog !== false) {
            $this->changelog->type = (string) $this->changelog->type;
            $this->changelog->text = (string) $this->changelog->text;
        }
        $this->majorCategory = (int) $this->majorCategory;
        $this->minorCategories = array_map("intval", array_unique($this->minorCategories));
        $this->keywords = explode(" ", $this->keywords);
        $this->perms = array_map("intval", array_unique($this->perms));
    }

    private function strictValidate() {
        if($this->mode === SubmitFormAjax::MODE_SUBMIT) {
            if(!Release::validateName($this->name, $error)) throw new SubmitException($error);
        }
        if(strlen($this->shortDesc) < Config::MIN_SHORT_DESC_LENGTH || strlen($this->shortDesc) > Config::MAX_SHORT_DESC_LENGTH) {
            throw new SubmitException("length(shortDesc) not in [" . Config::MIN_SHORT_DESC_LENGTH . "," . Config::MAX_SHORT_DESC_LENGTH . "]");
        }
        if(Meta::getAdmlv() <= Meta::ADMLV_REVIEWER) $this->official = false;
        if(!in_array($this->description->type, ["txt", "sm", "gfm"], true)) {
            throw new SubmitException("Invalid description.type");
        }
        if(strlen($this->description->text) < Config::MIN_DESCRIPTION_LENGTH) {
            throw new SubmitException("length(description.text) < " . Config::MIN_DESCRIPTION_LENGTH);
        }
        if($this->mode !== SubmitFormAjax::MODE_EDIT && !Release::validateVersion($this->buildInfo->projectId, $this->version, $error)) {
            throw new SubmitException($error);
        }
        if($this->mode !== SubmitFormAjax::MODE_EDIT && $this->outdated) throw new SubmitException("Why would you submit an outdated version?");
        if($this->lastValidVersion !== false) {
            if(!in_array($this->changelog->type, ["txt", "sm", "gfm"], true)) {
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
        foreach(count($deps) === 0 ? [] : Mysql::arrayQuery("SELECT projectId, releaseId, name, version, state FROM releases WHERE releaseId IN (%s)",
            ["i", array_keys($deps)]) as $row) {
            if(Release::STATE_REJECTED >= (int) $row["state"]) throw new SubmitException("dependency release({$row["releaseId"]}).state <= REJECTED");
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
        if(count($deps) > 0) throw new SubmitException("dependency release(" . array_keys($deps)[0] . ") does not exist. Did one of your dependencies get deleted?");
        foreach($this->requires as $require) {
            if(!isset($require->type, $require->details, $require->isRequire)) {
                throw new SubmitException("Malformed requirement " . json_encode($require));
            }
            if(!isset(PluginRequirement::$CONST_TO_DETAILS[$require->type])) {
                throw new SubmitException("Unknown requirement type $require->type");
            }
        }
        $this->spoons = SubmitFormAjax::apisToRanges(SubmitFormAjax::rangesToApis($this->spoons)); // validation and cleaning
        if(count($this->spoons) === 0) throw new SubmitException("Missing supported API versions");
        if($this->license->type !== "custom") {
            $knownLicenses = json_decode(Curl::curlGet("https://spdx.org/licenses/licenses.json", "Accept: application/json"), true);
            $isValidLicense = false;
            foreach($knownLicenses["licenses"] as $license) {
                if($license["licenseId"] === $this->license->type and $license["isOsiApproved"] === true){
                    $isValidLicense = true;
                    break;
                }
            }
            if(!$isValidLicense) {
                throw new SubmitException("Unknown license {$this->license->type}");
            }
        }
        if(count($this->authors) === 0) {
            throw new SubmitException("At least one producer must be provided.");
        }
        $this->checkAuthorNames();
    }

    private function checkAuthorNames() {
        $names = [];
        if(count($this->authors) === 0) return;
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

        $authorData = GitHub::ghGraphQL($query, Session::getInstance()->getAccessToken(), $names);
        foreach($authorData->data as $k => $user) {
            if($user === null) throw new SubmitException("No GitHub user called {$names[$k]}");
            $uid = (int) substr($k, 1);
            if($uid !== (int) $user->uid) throw new SubmitException("user(id:$uid) <> user(name:{$names[$k]})");
        }
    }

    public function resourcify() {
        $this->description = ResourceManager::getInstance()->storeArticle($this->description->type, $this->description->text, $this->repoInfo->full_name, "poggit.release.desc");
        if($this->changelog !== false) {
            $this->changelog = ResourceManager::getInstance()->storeArticle($this->changelog->type, $this->changelog->text, $this->repoInfo->full_name, "poggit.release.chlog");
        }
        $this->license->custom = $this->license->type === "custom" ? ResourceManager::getInstance()->storeArticle("txt", $this->license->custom, null, "poggit.release.license") : null;
    }

    public function processArtifact() {
        if($this->mode === SubmitFormAjax::MODE_EDIT) {
            $this->artifact = $this->refRelease->artifact;
            return;
        }

        $artifactPath = ResourceManager::getInstance()->createResource("phar", "application/octet-stream", [], $artifact, 315360000, "poggit.release.artifact", -1);
        copy($this->buildInfo->devBuildRsrPath, $artifactPath);
        $pharUrl = "phar://" . str_replace(DIRECTORY_SEPARATOR, "/", realpath($artifactPath)) . "/";
        $py = yaml_parse(file_get_contents($pharUrl . "plugin.yml"));
        $py["name"] = $this->name;
        $py["version"] = $this->version;
        $py["api"] = SubmitFormAjax::rangesToApis($this->spoons);
        file_put_contents($pharUrl . "plugin.yml", yaml_emit($py));

        $licenses = ["LICENSE", "LICENSE.md", "LICENSE.MD", "LICENSE.txt", "LICENSE.TXT", "license", "license.md", "license.MD", "license.txt", "license.TXT"];
        $licenseFound = false;
        foreach($licenses as $license){
            if(is_file($pharUrl . $license)){
                $licenseFound = true;
                break;
            }
        }

        if($licenseFound === false && $this->license->type !== null && $this->license->type !== "custom" && $this->license->type !== "none") {
            $templateText = json_decode(Curl::curlGet("https://spdx.org/licenses/{$this->license->type}.json", "Accept: application/json"), true)["standardLicenseTemplate"];
            $templateText = preg_replace_callback_array([
                    '/<<beginOptional>>(\X*?)<<endOptional>>/i' =>
                        static fn(array $match): string => $match[1] ?? "",
                    '/<<var;name="\w{0,20}";original="(.+?)";match="\X+?">>/i' =>
                        fn(array $match): string => str_ireplace([
                            '<year>',
                            '<copyright holders>',
                            '<name of author>',
                            '<owner>',
                            '<program>',
                            '[year]',
                            '[copyright holders]',
                            '[name of author]',
                            '[owner]',
                            '[program]'
                        ], [
                            date("Y"),
                            implode(', ', array_map(static fn(stdClass $obj): string => $obj->name, $this->authors)),
                            implode(', ', array_map(static fn(stdClass $obj): string => $obj->name, $this->authors)),
                            implode(', ', array_map(static fn(stdClass $obj): string => $obj->name, $this->authors)),
                            $this->name,
                            date("Y"),
                            implode(', ', array_map(static fn(stdClass $obj): string => $obj->name, $this->authors)),
                            implode(', ', array_map(static fn(stdClass $obj): string => $obj->name, $this->authors)),
                            implode(', ', array_map(static fn(stdClass $obj): string => $obj->name, $this->authors)),
                            $this->name
                        ], stripcslashes($match[1] ?? "")),
                ],
                $templateText, -1, $_, PREG_UNMATCHED_AS_NULL);
            file_put_contents($pharUrl . "LICENSE", $templateText);
        }
        $phar = new Phar($artifactPath);
        $phar->setMetadata(array_merge($phar->getMetadata(), [
            "poggitRelease" => [
                "date" => time(),
                "official" => $this->official,
                "preRelease" => $this->preRelease,
                "outdated" => $this->outdated,
                "majorCategory" => Release::$CATEGORIES[$this->majorCategory],
                "minorCategories" => array_map(function($cat) {
                    return Release::$CATEGORIES[$cat];
                }, $this->minorCategories),
                "keywords" => $this->keywords,
                "requires" => $this->requires,
                "license" => $this->license->type,
                "perms" => array_map(function($perm) {
                    return Release::$PERMISSIONS[$perm];
                }, $this->perms),
                "producers" => $this->authors,
                "link" => Meta::getSecret("meta.extPath") . "r/$artifact/$this->name.phar"
            ]
        ]));
        $this->artifact = $artifact;
        Mysql::query("UPDATE resources SET fileSize = ? WHERE resourceId = ?", "ii", filesize($artifactPath), $artifact);
        // TODO add compressed artifact here
    }

    public function save(): int {
        if($this->mode === SubmitFormAjax::MODE_EDIT) {
            $releaseId = $this->refRelease->releaseId;
            $toSet = [
                "shortDesc" => ["s", $this->shortDesc],
                "description" => ["i", $this->description],
                "changelog" => ["i", $this->changelog],
                "license" => ["s", $this->license->type],
                "licenseRes" => ["i", $this->license->custom],
                "flags" => ["i", $this->getFlags()]
            ];
            if($this->refRelease->state === Release::STATE_REJECTED) {
                throw new SubmitException("Can't edit rejected release");
            } elseif($this->refRelease->state === Release::STATE_DRAFT) {
                if($this->action === "submit") {
                    $toSet["state"] = ["i", Release::STATE_SUBMITTED];
                }
            } elseif($this->refRelease->state === Release::STATE_SUBMITTED) {
                if($this->action === "draft") {
                    $toSet["state"] = ["i", Release::STATE_DRAFT];
                }
            } else {
                if($this->action !== "submit") {
                    throw new SubmitException("Can't change checked release to draft");
                }
            }
            $query = "UPDATE releases SET ";
            $types = "";
            $args = [];
            foreach($toSet as $k => list($t, $v)) {
                $query .= "`$k` = ?,";
                assert(strlen($t) === 1);
                $types .= $t;
                $args[] = $v;
            }
            $query = substr($query, 0, -1) . " WHERE releaseId = ?";
            $types .= "i";
            $args[] = $releaseId;
            Mysql::query($query, $types, ...$args);

            $this->deleteReleaseMeta($releaseId);
        } else {
            $targetState = $this->action === "submit" ? Release::STATE_SUBMITTED : Release::STATE_DRAFT;
            $duplicateVersions = Mysql::query("SELECT releaseId FROM releases WHERE name = ? AND version = ? AND state <= ? ORDER BY buildId DESC LIMIT 1", "ssi", $this->name, $this->version, Release::STATE_REJECTED);
            if(count($duplicateVersions) > 0) {
                $dupeVersionId = $duplicateVersions[0]["releaseId"];
                Mysql::query("DELETE FROM releases WHERE releaseId = ?", "i", $dupeVersionId);
                $this->deleteReleaseMeta($dupeVersionId);
            }
            //TODO Delete parent release ID
            $releaseId = Mysql::query("INSERT INTO releases
            (name, shortDesc, artifact, projectId, buildId, version, description, icon, changelog, license, licenseRes, flags, state, parent_releaseId)
     VALUES (?   , ?        , ?       , ?        , ?      , ?      , ?          , ?   , ?        , ?      , ?         , ?    , ?    , ?)", str_replace(["\n", " "], "", "
             s     s          i         i          i        s        i            s     i          s        i           i      i      i"),
                $this->name, $this->shortDesc, $this->artifact, $this->buildInfo->projectId, $this->buildInfo->buildId,
                $this->version, $this->description, $this->icon ?: null, $this->changelog, $this->license->type, $this->license->custom,
                $this->getFlags(), $targetState, null)->insert_id;
        }

        Mysql::query("DELETE FROM release_categories WHERE projectId = ?", "i", $this->buildInfo->projectId); // categories
        $first = true;
        Mysql::insertBulk("release_categories", [
            "projectId" => "i",
            "category" => "i",
            "isMainCategory" => "i",
        ], array_merge([$this->majorCategory], $this->minorCategories), function($cat) use (&$first) {
            $ret = [$this->buildInfo->projectId, $cat, $first ? 1 : 0];
            $first = false;
            return $ret;
        });

        Mysql::query("DELETE FROM release_authors WHERE projectId = ?", "i", $this->buildInfo->projectId); // authors
        Mysql::insertBulk("release_authors", [
            "projectId" => "i",
            "uid" => "i",
            "name" => "s",
            "level" => "i"
        ], $this->authors, function($author) {
            return [$this->buildInfo->projectId, $author->uid, $author->name, $author->level];
        });

        Mysql::query("DELETE FROM release_keywords WHERE projectId = ?", "i", $this->buildInfo->projectId); // keywords
        Mysql::insertBulk("release_keywords", [
            "projectId" => "i",
            "word" => "s"
        ], $this->keywords, function($keyword) {
            return [$this->buildInfo->projectId, $keyword];
        });

        Mysql::insertBulk("release_deps", [
            "releaseId" => "i",
            "name" => "s",
            "version" => "s",
            "depRelId" => "i",
            "isHard" => "i",
        ], $this->deps, function($dep) use ($releaseId) {
            return [$releaseId, $dep->name, $dep->version, $dep->depRelId, $dep->required];
        }); // deps
        Mysql::insertBulk("release_reqr", [
            "releaseId" => "i",
            "type" => "i",
            "details" => "s",
            "isRequire" => "i"
        ], $this->requires, function($require) use ($releaseId) {
            return [$releaseId, $require->type, $require->details, $require->isRequire];
        }); // requires
        Mysql::insertBulk("release_spoons", [
            "releaseId" => "i",
            "since" => "s",
            "till" => "s",
        ], $this->spoons, function($spoon) use ($releaseId) {
            return [$releaseId, $spoon[0], $spoon[1]];
        }); // spoons
        Mysql::insertBulk("release_perms", [
            "releaseId" => "i",
            "val" => "i"
        ], $this->perms, function($perm) use ($releaseId) {
            return [$releaseId, $perm];
        }); // perms

        return $releaseId;
    }

    public function getFlags(): int {
        $flags = 0;
        if($this->preRelease) $flags |= Release::FLAG_PRE_RELEASE;
        if($this->outdated) $flags |= Release::FLAG_OUTDATED;
        if($this->official) $flags |= Release::FLAG_OFFICIAL;
        if($this->abandoned) $flags |= Release::FLAG_ABANDONED;
        return $flags;
    }

    private function deleteReleaseMeta(int $releaseId) {
        Mysql::query("DELETE FROM release_deps WHERE releaseId = ?", "i", $releaseId);
        Mysql::query("DELETE FROM release_reqr WHERE releaseId = ?", "i", $releaseId);
        Mysql::query("DELETE FROM release_spoons WHERE releaseId = ?", "i", $releaseId);
        Mysql::query("DELETE FROM release_perms WHERE releaseId = ?", "i", $releaseId);
    }
}
