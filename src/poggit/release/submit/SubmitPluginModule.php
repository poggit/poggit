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
use poggit\ci\builder\ProjectBuilder;
use poggit\Meta;
use poggit\module\Module;
use poggit\release\PluginRelease;
use poggit\release\submit\entry\BoolSubmitFormEntry;
use poggit\release\submit\entry\DroplistSubmitFormEntry;
use poggit\release\submit\entry\ExpandedMultiSelectSubmitFormEntry;
use poggit\release\submit\entry\HybridTextSubmitFormEntry;
use poggit\release\submit\entry\MultiSelectSubmitFormEntry;
use poggit\release\submit\entry\StringSubmitFormEntry;
use poggit\release\submit\entry\SubmitFormEntry;
use poggit\release\submit\entry\TableSubmitFormEntry;
use poggit\resource\ResourceManager;
use poggit\utils\internet\Curl;
use poggit\utils\internet\GitHubAPIException;
use poggit\utils\internet\Mysql;
use poggit\utils\lang\Lang;
use poggit\utils\PocketMineApi;

class SubmitPluginModule extends Module {
    const MODE_SUBMIT = 0;
    const MODE_UPDATE = 1;
    const MODE_EDIT = 2;

    private $buildRepoOwner;
    private $buildRepoName;
    private $buildProjectName;
    private $buildNumber;
    /** @var \stdClass */
    private $buildInfo;
    /** @var \stdClass */
    private $repoInfo;
    /** @var \stdClass */
    private $refRelease;
    /** @var int */
    private $mode;
    /** @var bool */
    private $needsChangelog;
    /** @var string|void */
    private $lastName;
    /** @var string|void */
    private $lastVersion;

    public function getName(): string {
        return "submit";
    }

    public function getAllNames(): array {
        return ["submit", "update", "edit"];
    }

    public function output() {
        $session = Session::getInstance();
        if(!$session->isLoggedIn()) Meta::redirect("login");
        $path = array_filter(explode("/", $this->getQuery(), 4), "string_not_empty");;
        if(count($path) === 0) Meta::redirect("https://youtu.be/SKaOPMT-aM8", true); // TODO write a proper help page
        if(count($path) < 4) Meta::redirect("ci/" . implode("/", $path));
        list($this->buildRepoOwner, $this->buildRepoName, $this->buildProjectName, $this->buildNumber) = $path;
        if($this->buildProjectName === "~") $this->buildProjectName = $this->buildRepoName;
        if(Lang::startsWith(strtolower($this->buildNumber), "dev:")) $this->buildNumber = substr($this->buildNumber, 4);
        if(!is_numeric($this->buildNumber)) Meta::redirect("ci/$this->buildRepoOwner/$this->buildRepoName/$this->buildProjectName");
        $this->buildNumber = (int) $this->buildNumber;
        try {
            $this->repoInfo = Curl::ghApiGet("repos/$this->buildRepoOwner/$this->buildRepoName", $session->getAccessToken(), ["Accept: application/vnd.github.drax-preview+json"]);
            if($this->repoInfo->private) $this->errorBadRequest("Only plugins built from public repos can be submitted");
        } catch(GitHubAPIException $e) {
            $this->errorNotFound();
        }
        $rows = Mysql::query("SELECT repoId, projectId, buildId, devBuildRsr, internal, type, path, buildTime, sha, branch,
            releaseId, (SELECT state FROM releases r2 WHERE r2.releaseId = t.releaseId) thisState,
            lastReleaseId, (SELECT state FROM releases r2 WHERE r2.releaseId = t.lastReleaseId) lastState,
                           (SELECT version FROM releases r2 WHERE r2.releaseId = t.lastReleaseId) lastVersion
                FROM (SELECT repos.repoId, projects.projectId, builds.buildId, builds.resourceId devBuildRsr, internal,
                    projects.type, projects.path, UNIX_TIMESTAMP(created) buildTime, sha, branch,
                    IFNULL((SELECT releaseId FROM releases WHERE releases.buildId = builds.buildId LIMIT 1), -1) releaseId,
                    IFNULL((SELECT releaseId FROM releases WHERE releases.projectId = projects.projectId ORDER BY creation DESC LIMIT 1), -1) lastReleaseId FROM builds
                INNER JOIN projects ON builds.projectId = projects.projectId
                INNER JOIN repos ON projects.repoId = repos.repoId
                WHERE repos.owner = ? AND repos.name = ? AND projects.name = ? AND builds.class = ? AND builds.internal = ?) t",
            "sssii", $this->buildRepoOwner, $this->buildRepoName, $this->buildProjectName, ProjectBuilder::BUILD_CLASS_DEV, $this->buildNumber);
        if(count($rows) !== 1) $this->errorNotFound();
        $this->buildInfo = (object) $rows[0];
        $this->buildInfo->repoId = (int) $this->buildInfo->repoId;
        $this->buildInfo->projectId = (int) $this->buildInfo->projectId;
        $this->buildInfo->buildId = (int) $this->buildInfo->buildId;
        $this->buildInfo->internal = (int) $this->buildInfo->internal;
        $this->buildInfo->devBuildRsr = (int) $this->buildInfo->devBuildRsr;
        $this->buildInfo->devBuildRsrPath = ResourceManager::pathTo($this->buildInfo->devBuildRsr, "phar");
        $this->buildInfo->type = (int) $this->buildInfo->type;
        $this->buildInfo->buildTime = (int) $this->buildInfo->buildTime;
        $this->buildInfo->releaseId = (int) $this->buildInfo->releaseId;
        $this->buildInfo->thisState = (int) $this->buildInfo->thisState;
        $this->buildInfo->lastReleaseId = (int) $this->buildInfo->lastReleaseId;
        $this->buildInfo->lastState = (int) $this->buildInfo->lastState;


        if($this->buildInfo->type !== ProjectBuilder::PROJECT_TYPE_PLUGIN) $this->errorBadRequest("Only plugin projects can be submitted");
        if($this->buildInfo->releaseId !== -1) {
            if(Meta::getModuleName() !== "edit") Meta::redirect("edit/$this->buildRepoOwner/$this->buildRepoName/$this->buildProjectName/$this->buildNumber");
            $this->mode = SubmitPluginModule::MODE_EDIT;
            $refReleaseId = $this->buildInfo->releaseId;
        } elseif($this->buildInfo->lastReleaseId !== -1) {
            if(Meta::getModuleName() !== "update") Meta::redirect("update/$this->buildRepoOwner/$this->buildRepoName/$this->buildProjectName/$this->buildNumber");
            $this->mode = SubmitPluginModule::MODE_UPDATE;
            $refReleaseId = $this->buildInfo->lastReleaseId;
        } else {
            if(Meta::getModuleName() !== "submit") Meta::redirect("submit/$this->buildRepoOwner/$this->buildRepoName/$this->buildProjectName/$this->buildNumber");
            $this->mode = SubmitPluginModule::MODE_SUBMIT;
            $refReleaseId = null;
        }

        $this->needsChangelog = false;
        foreach(Mysql::query("SELECT name, releaseId, state, version, internal FROM releases
                INNER JOIN builds ON releases.buildId = builds.buildId WHERE releases.projectId = ? ORDER BY creation ASC", "i", $this->buildInfo->projectId) as $row) {
            $state = (int) $row["state"];
            $internal = (int) $row["internal"];
            $releaseLink = Meta::root() . "p/{$row["name"]}/{$row["version"]}";
            if($internal > $this->buildInfo->internal && $state > PluginRelease::RELEASE_STATE_REJECTED) {
                $this->errorBadRequest("You already released <a href='$releaseLink'>v{$row["version"]}</a>, based on build #$internal, so you can't make a release from build #{$this->buildInfo->internal}", true);
            }
            if($internal === $this->buildInfo->internal) {
                if($state === PluginRelease::RELEASE_STATE_REJECTED) {
                    $this->errorBadRequest("You previously tried to release <a href='$releaseLink'>v{$row["version"]}</a> from this build, but it was rejected. If you wish to submit this build again, please delete it first.", true);
                }
            }
            if($state === PluginRelease::RELEASE_STATE_SUBMITTED) {
                $this->errorBadRequest("You have previoiusly submitted <a href='$releaseLink'>v{$row["version"]}</a>, which has
                    not been approved yet. Please delete the previous release before releasing new versions", true);
            }
            if($state >= PluginRelease::RELEASE_STATE_CHECKED) {
                $this->needsChangelog = true;
                $this->lastName = $row["name"];
                $this->lastVersion = $row["version"];
            }
        }

        if(!($this->mode === SubmitPluginModule::MODE_SUBMIT && $this->repoInfo->permissions->admin or
            $this->mode === SubmitPluginModule::MODE_UPDATE && $this->repoInfo->permissions->push or
            $this->mode === SubmitPluginModule::MODE_EDIT && $this->repoInfo->permissions->push)) {
            $this->errorAccessDenied("You must have at least " . ($this->mode === SubmitPluginModule::MODE_SUBMIT ? "admin" : "push") . " access to a repo to release projects in it");
        }


        if($refReleaseId === null) {
            $this->refRelease = new class {
                function __get($name) {
                    if($name === "recursive attribute?") {
                        return $this;
                    }
                    return null;
                }
            };
        } else {
            $this->refRelease = (object) Mysql::query("SELECT releaseId, name, shortDesc, version, state, buildId, flags,
                    description, descr.type desctype, IFNULL(descr.relMd, 1) descrMd,
                    changelog, chlog.type changelogType, IFNULL(chlog.relMd, 1) chlogMd,
                    license, licenseRes, IF(licenseRes IS NULL, 1, IFNULL(lic.relMd, 1)) licMd,
                    UNIX_TIMESTAMP(creation) submitTime,
                    GROUP_CONCAT((SELECT DISTINCT word FROM release_keywords rk WHERE rk.projectId = releases.projectId) SEPARATOR ' ') keywords,
                    GROUP_CONCAT((SELECT val FROM release_perms WHERE release_perms.releaseId = releases.releaseId) SEPARATOR ',') perms
                FROM releases
                    LEFT JOIN resources descr ON descr.resourceId = releases.description
                    LEFT JOIN resources chlog ON chlog.resourceId = releases.changelog
                    LEFT JOIN resources lic ON lic.resourceId = IFNULL(releases.licenseRes, 1)
                WHERE releaseId = ?", "i", $refReleaseId)[0];
            $this->refRelease->releaseId = (int) $this->refRelease->releaseId;
            $this->refRelease->parent_releaseId = (int) $this->refRelease->parent_releaseId;
            $this->refRelease->description = (int) $this->refRelease->description;
            $this->refRelease->descrMd = $this->refRelease->descrMd === ResourceManager::NULL_RESOURCE ? null : $this->refRelease->descrMd;
            $this->refRelease->changelog = (int) $this->refRelease->changelog;
            if($this->refRelease->changelog === ResourceManager::NULL_RESOURCE) {
                $this->refRelease->changelog = null;
                $this->refRelease->changelogType = null;
                $this->refRelease->chlogMd = null;
            } else {
                $this->refRelease->chlogMd = $this->refRelease->chlogMd === ResourceManager::NULL_RESOURCE ? null : $this->refRelease->chlogMd;
            }
            $this->refRelease->state = (int) $this->refRelease->state;
            $this->refRelease->buildId = (int) $this->refRelease->buildId;
            $this->refRelease->licenseRes = $this->refRelease->license === "custom" ? (int) $this->refRelease->licenseRes : null;
            $this->refRelease->licMd = $this->refRelease->licMd === ResourceManager::NULL_RESOURCE ? null : $this->refRelease->licMd;
            $this->refRelease->flags = (int) $this->refRelease->flags;
            $this->refRelease->submitTime = (int) $this->refRelease->submitTime; // TODO remember to update submitTime when setting Draft to Submitted
            $this->refRelease->keywords = explode(" ", $this->refRelease->keywords);
            $this->refRelease->perms = array_map("intval", explode(",", $this->refRelease->perms));
            $this->refRelease->categories = [];
            foreach(Mysql::query("SELECT category, IF(isMainCategory, 1, 0) FROM release_categories WHERE projectId = ?", "i", $this->buildInfo->projectId) as $row) {
                if((int) $row["isMainCategory"]) {
                    $this->refRelease->mainCategory = (int) $row["category"];
                } else {
                    $this->refRelease->categories[] = (int) $row["category"];
                }
            }
            foreach(Mysql::query("SELECT since, till FROM release_spoons WHERE releaseId = ?", "i", $refReleaseId) as $row) {
                $this->refRelease->spoons[] = [$row["since"], $row["till"]];
            }
            $this->refRelease->authors = [];
            foreach(Mysql::query("SELECT uid, name, level FROM release_authors WHERE projectId = ?", "i", $this->buildInfo->projectId) as $row) {
                $this->refRelease->authors[(int) $row["level"]][(int) $row["uid"]] = $row["name"];
            }
            $this->refRelease->childAssocs = [];
            foreach(Mysql::query("SELECT releaseId, name, version FROM releases WHERE parent_releaseId = ?", "i", $refReleaseId) as $child) {
                $this->refRelease->childAssocs[$child["name"]] = (object) [
                    "releaseId" => $child["releaseId"],
                    "version" => $child["version"]
                ];
            }
            $this->refRelease->deps = [];
            foreach(Mysql::query("SELECT name, version, depRelId, IF(isHard, 1, 0) isHard FROM release_deps WHERE releaseId = ?", "i", $refReleaseId) as $row) {
                $row["depRelId"] = (int) $row["depRelId"];
                $row["isHard"] = (bool) (int) $row["isHard"];
                $this->refRelease->deps[] = (object) $row;
            }
            $this->refRelease->requires = [];
            foreach(Mysql::query("SELECT type, details, IF(isRequire, 1, 0) isRequire FROM release_reqr WHERE releaseId = ?", "i", $refReleaseId) as $row) {
                $row["type"] = (int) $row["type"];
                $row["isRequire"] = (bool) (int) $row["isRequire"];
                $this->refRelease->requires[] = (object) $row;
            }
        }
    }

    private function getFields() {
        $pluginYml = @yaml_parse_url("phar://" . $this->buildInfo->devBuildRsrPath . "/plugin.yml");
        if(!is_array($pluginYml)) $this->errorBadRequest("Cannot submit plugin with error in plugin.yml");

        $fields = [];
        $fields[] = new StringSubmitFormEntry("submit2-name-input", "Plugin Name", <<<EOD
The name of the plugin. This will replace the <code>name</code> attribute in plugin.yml in the release phar, and will be
used in the URL and display name of this release. Therefore, this must not duplicate any other existing plugins.<br/>
The plugin name must not be changed <em>under any circumstances</em> once the first release
EOD
            , $this->refRelease->name, $pluginYml["name"] ?? null, SubmitFormEntry::PREFER_LAST_RELEASE_VALUE);
        $fields[] = new StringSubmitFormEntry("submit2-shortdesc-input", "Synopsis", <<<EOD
A one-line brief description of your plugin. One or two <em>simple</em> and <em>attractive</em> sentences describing your
plugin.
EOD
            , $this->refRelease->shortDesc, $this->repoInfo->description, SubmitFormEntry::PREFER_LAST_RELEASE_VALUE);
        $fields[] = new StringSubmitFormEntry("submit2-version-input", "Plugin Version", <<<EOD
The version of this release. The version <em>must be named according to <a href="http://semver.org">Semantic Versioning</a></em>,
i.e. the version must consist of two or three numbers, optionally with prerelease information behind a hyphen, e.g.
<code>1.0</code>, <code>2.0.1</code>, <code>3.0.0-beta</code>, <code>4.7.0-beta.3</code>. Note that adding build
metadata behind a <code>+</code> in the version is discouraged due to URL encoding inconvenience.<br/>
This version will replace the <code>version</code> attribute in plugin.yml in the release phar, so this doesn't have to
be same as that in plugin.yml.
EOD
            , $this->refRelease->version, $pluginYml["version"] ?? null, SubmitFormEntry::PREFER_SRC_DETECTED_VALUE);
        $fields[] = new HybridTextSubmitFormEntry("submit2-desc", "Description", <<<EOD
A detailed description of your release. You may link to other sites for documentation in this page, but you must include
a basic description here in case the other sites are down.<br/>
It is recommended that the description includes the following:<br/>
<ul>
    <li>Features (what this plugin does)</li>
    <li>Usage (command syntax, or other special ways you need to use to interact with this plugin)</li>
    <li>Protips (if the usage of this plugin is not straightforward, you can give a few ideas how this plugin can be used)</li>
</ul>
While you may import README from your repo, make sure you don't accidentally include irrelevant things like download
links in the description.<br/>
Plugins with insufficient description may be rejected.
EOD
            , [
                "type" => $this->refRelease->desctype,
                "text" => $this->refRelease->desctype === "html" && $this->refRelease->descrMd !== null ?
                    ResourceManager::read($this->refRelease->descrMd, "md") : ResourceManager::read($this->refRelease->description, $this->refRelease->desctype)
            ], null, SubmitFormEntry::PREFER_LAST_RELEASE_VALUE);
        if($this->needsChangelog) {
            $lastReleaseLink = Meta::root() . "p/" . $this->lastName . "/" . $this->lastVersion;
            $fields[] = new HybridTextSubmitFormEntry("submit2-chlog", "What's new", <<<EOD
List important changes since the <a href="$lastReleaseLink">last release</a> here.<br/>
Make sure you update the description too.
EOD
                , null, null, SubmitFormEntry::PREFER_SRC_DETECTED_VALUE);
        }
        $fields[] = new DroplistSubmitFormEntry("submit2-license", "License", <<<EOD
The license your plugin is released with.<br/>
You should use the same one used in your source code.
EOD
            , $this->refRelease->license, $this->repoInfo->license->key, SubmitFormEntry::PREFER_SRC_DETECTED_VALUE);
        $fields[] = new BoolSubmitFormEntry("submit2-prerelease", "Pre-release?", <<<EOD
Pre-release versions will not be listed by default. This is for users to have a "semi-stable" preview version of your
updates.<br/>
Pre-release versions are less likely to be rejected, since a higher amount of bugs are tolerable.
EOD
            , ($this->refRelease->flags & PluginRelease::RELEASE_FLAG_PRE_RELEASE) > 0, null, SubmitFormEntry::PREFER_LAST_RELEASE_VALUE);
        $fields[] = new DroplistSubmitFormEntry("submit2-major-category", "Major Category", <<<EOD
The category of your plugin to be listed in
EOD
            , $this->refRelease->mainCategory, null, SubmitFormEntry::PREFER_LAST_RELEASE_VALUE, PluginRelease::$CATEGORIES);
        $fields[] = new MultiSelectSubmitFormEntry("submit2-minor-categories", "Minor Categories", <<<EOD
Users watching these categories will be notified when you submit or update this plugin.
EOD
            , $this->refRelease->categories, null, SubmitFormEntry::PREFER_LAST_RELEASE_VALUE, PluginRelease::$CATEGORIES);
        $root = Meta::root();
        $fields[] = new StringSubmitFormEntry("submit2-keywords", "Keywords", <<<EOD
A space-separated list of keywords. Users may search this plugin using keywords. Add some generic keywords, just like
<a href="{$root}gh.topics" target="_blank">Topics in GitHub repositories</a>.
EOD
            , implode(" ", $this->refRelease->keywords), implode(" ", $this->repoInfo->topics), SubmitFormEntry::PREFER_LAST_RELEASE_VALUE);

        $apiVersions = array_keys(PocketMineApi::$VERSIONS);
        $spoonVersions = [];
        foreach($apiVersions as $v) {
            $spoonVersions[$v] = $v;
        }

        $fields[] = new TableSubmitFormEntry("submit2-spoons", "Supported API versions", <<<EOD
The PocketMine API versions<a href="{$root}gh.pmmp" target="_blank"><img class='gh-logo' src='/res/ghMark.png' width='12'/></a>
supported by this plugin. This will replace the plugin.yml <code>api</code> attribute. You cannot edit this unless you
submit a new build.<br/>
If you include an API version that your plugin won't work on, this plugin will be rejected.
EOD
            , $this->refRelease->spoons, SubmitPluginModule::apisToRanges((array) ($pluginYml["api"] ?? [])), SubmitFormEntry::PREFER_SRC_DETECTED_VALUE
        );

        $detectedDeps = [];
        foreach((array) ($pluginYml["depend"] ?? []) as $name) {
            $detectedDeps[$name] = true;
        }
        foreach((array) ($pluginYml["softdepend"] ?? []) as $name) {
            $detectedDeps[$name] = false;
        }
        $qmarks = substr(str_repeat(",?", count($detectedDeps)), 1);
        $rows = Mysql::query("SELECT t.name, r.version, t.releaseId FROM
                (SELECT name, MAX(releaseId) releaseId FROM releases WHERE state >= ? AND name IN ($qmarks) GROUP BY name) t
                INNER JOIN releases r ON r.releaseId = t.releaseId", "i" . str_repeat("s", count($detectedDeps)), PluginRelease::RELEASE_STATE_SUBMITTED, ...array_keys($detectedDeps));
        foreach($rows as $row) {
            $row["required"] = $detectedDeps[$row["name"]];
            $detectedDeps[$row["name"]] = $row;
        }
        foreach($detectedDeps as $name => $data) {
            if($data === true) $this->errorBadRequest("The plugin requires the dependency \"$name\", but it does not exist");
            if($data === false) unset($detectedDeps[$name]);
        }
        $fields[] = new TableSubmitFormEntry("submit2-deps", "Dependencies", <<<EOD
If this plugin <em>requires</em> another plugin to run, add it as a <em>Required</em> plugin.<br/>
If this plugin <em>optionally</em> runs with another plugin, add it as an <em>Optional</em> plugin.<br/>
If the required plugin has not been submitted (and not rejected) on Poggit Release yet, you must not submit this plugin.
This plugin will be rejected if the plugins it requires are rejected (and have no other available versions).<br/>
Note that dependencies do <em>not</em> mean what plugins you <em>recommend</em> the user to use, e.g. you don't need to
add permission plugins as dependencies just because your plugin checks players' permissions. Required dependencies are
only for plugins that you <em>use their API/handle their events</em>, and your plugin will <em>crash</em> if the plugin
isn't present; Optional dependencies are only for plugins that you <em>use their API/handle their events</em> but
<em>will gracefully skip the related operations and still serve its basic features</em>.<br/>
Required and Optional plugin should correspond to the <code>depend</code> and <code>softdepend</code> attributes in plugin.yml.
Poggit will <strong>not</strong> automatically replace such values in plugin.yml, but under normal circumstances, they
should be the same.
EOD
            , $this->refRelease->deps, $detectedDeps, SubmitFormEntry::PREFER_SRC_DETECTED_VALUE);

        $fields[] = new ExpandedMultiSelectSubmitFormEntry("submit2-perms", "Permissions", <<<EOD
What does this plugin do?
EOD
            , PluginRelease::$PERMISSIONS, $this->refRelease->perms);

        $fields[] = new TableSubmitFormEntry("submit2-requires", "Manual setup", <<<EOD
<em>Requirements</em> refer to things that the user <em>must</em> manually setup. This usually refers to external
services used by the plugin, or confidential information that varies on each server.
<em>Enhancements</em> are similar to Requirements, except that they are optional &mdash; the plugin will continue to work
normally even without this manual setup.
EOD
, $this->refRelease->requires, null, SubmitFormEntry::PREFER_LAST_RELEASE_VALUE);

        return $fields;
    }

    private static function apisToRanges(array $input) {
        $versions = array_flip(array_keys(PocketMineApi::$VERSIONS));
        $sortedInput = [];
        foreach($input as $api) {
            $sortedInput[$api] = $versions[$api];
        }
        ksort($sortedInput, SORT_NUMERIC);

        $ranges = [];

        foreach(PocketMineApi::$VERSIONS as $api => $data) {
            if(!isset($start, $end)) {
                if(isset($sortedInput[$api])) {
                    $start = $api;
                    $end = $api;
                }
            } else {
                if(!$data["incompatible"]) { // what was I thinking...
                    $end = $api;
                } else {
                    $ranges[] = [$start, $end];
                    unset($start, $end);
                }
            }
        }
        if(isset($start, $end)) {
            $ranges[] = [$start, $end];
        }

        return $ranges;
    }

    private static function rangesToApis(array $input) {
        $flatInput = [];
        $versions = array_keys(PocketMineApi::$VERSIONS);
        foreach($input as list($start, $end)) {
            $startNumber = array_search($start, $versions);
            $endNumber = array_search($end, $versions);
            for($i = $startNumber; $i <= $endNumber; ++$i) {
                $flatInput[$versions[$i]] = $i;
            }
        }
        asort($flatInput, SORT_NUMERIC);

        $output = [];
        $carry = false;
        foreach(PocketMineApi::$VERSIONS as $api => $data) {
            if($data["incompatible"]) $carry = false;
            if(isset($flatInput[$api])) {
                if($carry) continue;
                $output[] = $flatInput[$api];
                $carry = true;
            }
        }

        return $carry;
    }
}
