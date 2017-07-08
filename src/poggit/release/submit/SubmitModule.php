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
use poggit\Mbd;
use poggit\Meta;
use poggit\module\Module;
use poggit\release\PluginRelease;
use poggit\release\PluginRequirement;
use poggit\resource\ResourceManager;
use poggit\utils\internet\Curl;
use poggit\utils\internet\GitHubAPIException;
use poggit\utils\internet\Mysql;
use poggit\utils\lang\Lang;
use poggit\utils\OutputManager;
use poggit\utils\PocketMineApi;

class SubmitModule extends Module {
    const MODE_SUBMIT = "submit";
    const MODE_UPDATE = "update";
    const MODE_EDIT = "edit";

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
    private $pluginYml;

    public function getName(): string {
        return "submit";
    }

    public function getAllNames(): array {
        return ["submit", "update", "edit"];
    }

    public function output() {
        $session = Session::getInstance();
        if(!$session->isLoggedIn()) Meta::redirect("login");
        $path = Lang::explodeNoEmpty("/", $this->getQuery(), 4);
        if(count($path) === 0) Meta::redirect("https://youtu.be/SKaOPMT-aM8", true); // TODO write a proper help page
        if(count($path) < 4) Meta::redirect("ci/" . implode("/", $path));
        list($this->buildRepoOwner, $this->buildRepoName, $this->buildProjectName, $this->buildNumber) = $path;
        if($this->buildProjectName === "~") $this->buildProjectName = $this->buildRepoName;
        if(Lang::startsWith(strtolower($this->buildNumber), "dev:")) $this->buildNumber = substr($this->buildNumber, 4);
        if(!is_numeric($this->buildNumber)) Meta::redirect("ci/$this->buildRepoOwner/$this->buildRepoName/$this->buildProjectName");
        $this->buildNumber = (int) $this->buildNumber;
        try {
            $this->repoInfo = Curl::ghApiGet("repos/$this->buildRepoOwner/$this->buildRepoName", $session->getAccessToken(), ["Accept: application/vnd.github.drax-preview+json,application/vnd.github.mercy-preview+json"]);
            Curl::clearGhUrls($this->repoInfo);
            if($this->repoInfo->private) $this->errorBadRequest("Only plugins built from public repos can be submitted");
            $this->buildRepoOwner = $this->repoInfo->owner->login;
            $this->buildRepoName = $this->repoInfo->name;
        } catch(GitHubAPIException $e) {
            $this->errorNotFound();
        }
        $rows = Mysql::query("SELECT repoId, projectId, buildId, devBuildRsr, internal, projectName, projectType, path, buildTime, sha, branch,
            releaseId, (SELECT state FROM releases r2 WHERE r2.releaseId = t.releaseId) thisState,
            lastReleaseId, (SELECT state FROM releases r2 WHERE r2.releaseId = t.lastReleaseId) lastState,
                           (SELECT version FROM releases r2 WHERE r2.releaseId = t.lastReleaseId) lastVersion
                FROM (SELECT repos.repoId, projects.projectId, builds.buildId, builds.resourceId devBuildRsr, internal,
                    projects.name projectName, projects.type projectType, projects.path, UNIX_TIMESTAMP(created) buildTime, sha, branch,
                    IFNULL((SELECT releaseId FROM releases WHERE releases.buildId = builds.buildId LIMIT 1), -1) releaseId,
                    IFNULL((SELECT releaseId FROM releases WHERE releases.projectId = projects.projectId ORDER BY creation DESC LIMIT 1), -1) lastReleaseId FROM builds
                INNER JOIN projects ON builds.projectId = projects.projectId
                INNER JOIN repos ON projects.repoId = repos.repoId
                WHERE repos.owner = ? AND repos.name = ? AND projects.name = ? AND builds.class = ? AND builds.internal = ?) t",
            "sssii", $this->buildRepoOwner, $this->buildRepoName, $this->buildProjectName, ProjectBuilder::BUILD_CLASS_DEV, $this->buildNumber);
        if(count($rows) !== 1) $this->errorNotFound();
        $this->buildInfo = (object) $rows[0];
        $this->buildProjectName = $this->buildInfo->projectName;
        $this->buildInfo->repoId = (int) $this->buildInfo->repoId;
        $this->buildInfo->projectId = (int) $this->buildInfo->projectId;
        $this->buildInfo->buildId = (int) $this->buildInfo->buildId;
        $this->buildInfo->internal = (int) $this->buildInfo->internal;
        $this->buildInfo->devBuildRsr = (int) $this->buildInfo->devBuildRsr;
        $this->buildInfo->devBuildRsrPath = ResourceManager::pathTo($this->buildInfo->devBuildRsr, "phar");
        $this->buildInfo->projectType = (int) $this->buildInfo->projectType;
        $this->buildInfo->buildTime = (int) $this->buildInfo->buildTime;
        $this->buildInfo->releaseId = (int) $this->buildInfo->releaseId;
        $this->buildInfo->thisState = (int) $this->buildInfo->thisState;
        $this->buildInfo->lastReleaseId = (int) $this->buildInfo->lastReleaseId;
        $this->buildInfo->lastState = (int) $this->buildInfo->lastState;


        if($this->buildInfo->projectType !== ProjectBuilder::PROJECT_TYPE_PLUGIN) $this->errorBadRequest("Only plugin projects can be submitted");
        if($this->buildInfo->releaseId !== -1) {
            if(Meta::getModuleName() !== "edit") Meta::redirect("edit/$this->buildRepoOwner/$this->buildRepoName/$this->buildProjectName/$this->buildNumber");
            $this->mode = SubmitModule::MODE_EDIT;
            $refReleaseId = $this->buildInfo->releaseId;
        } elseif($this->buildInfo->lastReleaseId !== -1) {
            if(Meta::getModuleName() !== "update") Meta::redirect("update/$this->buildRepoOwner/$this->buildRepoName/$this->buildProjectName/$this->buildNumber");
            $this->mode = SubmitModule::MODE_UPDATE;
            $refReleaseId = $this->buildInfo->lastReleaseId;
        } else {
            if(Meta::getModuleName() !== "submit") Meta::redirect("submit/$this->buildRepoOwner/$this->buildRepoName/$this->buildProjectName/$this->buildNumber");
            $this->mode = SubmitModule::MODE_SUBMIT;
            $refReleaseId = null;
        }

        $this->needsChangelog = false;
        foreach(Mysql::query("SELECT name, releaseId, state, version, internal FROM releases
                INNER JOIN builds ON releases.buildId = builds.buildId WHERE releases.projectId = ? ORDER BY creation ASC", "i", $this->buildInfo->projectId) as $row) {
            $state = (int) $row["state"];
            $internal = (int) $row["internal"];
            $releaseLink = Meta::root() . "p/{$row["name"]}/{$row["version"]}";
            if($internal > $this->buildInfo->internal && $state > PluginRelease::RELEASE_STATE_REJECTED) {
                $this->errorBadRequest("You have already released <a target='_blank' href='$releaseLink'>v{$row["version"]}</a> based on build #$internal, so you can't make a release from an older build #{$this->buildInfo->internal}", false);
                // FIXME Allow editing old releases
            }
            if($internal === $this->buildInfo->internal) {
                if($state === PluginRelease::RELEASE_STATE_REJECTED) {
                    $this->errorBadRequest("You previously tried to release <a target='_blank' href='$releaseLink'>v{$row["version"]}</a> from this build, but it was rejected. If you wish to submit this build again, please delete it first.", false);
                }
            }
            if($state === PluginRelease::RELEASE_STATE_SUBMITTED) {
                $this->errorBadRequest("You have previoiusly submitted <a target='_blank' href='$releaseLink'>v{$row["version"]}</a>, which has
                    not been approved yet. Please delete the previous release before releasing new versions", false);
            }
            if($state >= PluginRelease::RELEASE_STATE_CHECKED) {
                $this->needsChangelog = true;
                $this->lastName = $row["name"];
                $this->lastVersion = $row["version"];
            }
        }

        if(!($this->mode === SubmitModule::MODE_SUBMIT && $this->repoInfo->permissions->admin or
            $this->mode === SubmitModule::MODE_UPDATE && $this->repoInfo->permissions->push or
            $this->mode === SubmitModule::MODE_EDIT && $this->repoInfo->permissions->push)) {
            $this->errorAccessDenied("You must have at least " . ($this->mode === SubmitModule::MODE_SUBMIT ? "admin" : "push") . " access to a repo to release projects in it");
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
            $this->refRelease = (object) Mysql::query("SELECT releaseId, parent_releaseId, name, shortDesc, version, state, buildId, flags,
                    description, descr.type desctype, IFNULL(descr.relMd, 1) descrMd,
                    changelog, chlog.type changelogType, IFNULL(chlog.relMd, 1) chlogMd,
                    license, licenseRes,
                    UNIX_TIMESTAMP(creation) submitTime,
                    (SELECT GROUP_CONCAT(DISTINCT word SEPARATOR ' ') FROM release_keywords rk WHERE rk.projectId = releases.projectId) keywords,
                    (SELECT GROUP_CONCAT(val SEPARATOR ',') FROM release_perms WHERE release_perms.releaseId = releases.releaseId) perms
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
            $this->refRelease->flags = (int) $this->refRelease->flags;
            $this->refRelease->submitTime = (int) $this->refRelease->submitTime; // TODO remember to update submitTime when setting Draft to Submitted
            $this->refRelease->keywords = explode(" ", $this->refRelease->keywords);
            $this->refRelease->perms = array_map("intval", explode(",", $this->refRelease->perms));
            $this->refRelease->categories = [];
            foreach(Mysql::query("SELECT category, IF(isMainCategory, 1, 0) isMain FROM release_categories WHERE projectId = ?", "i", $this->buildInfo->projectId) as $row) {
                if((bool) (int) $row["isMain"]) {
                    $this->refRelease->mainCategory = (int) $row["category"];
                } else {
                    $this->refRelease->categories[] = (int) $row["category"];
                }
            }
            $this->refRelease->spoons = [];
            foreach(Mysql::query("SELECT since, till FROM release_spoons WHERE releaseId = ?", "i", $refReleaseId) as $row) {
                $this->refRelease->spoons[] = [$row["since"], $row["till"]];
            }
            $this->refRelease->authors = [];
            foreach(Mysql::query("SELECT uid, name, level FROM release_authors WHERE projectId = ?", "i", $this->buildInfo->projectId) as $row) {
                $this->refRelease->authors[] = (object) [
                    "uid" => (int) $row["uid"],
                    "name" => $row["name"],
                    "level" => (int) $row["level"]
                ];
            }
            $this->refRelease->childAssocs = [];
            foreach(Mysql::query("SELECT releaseId, name, version FROM releases WHERE parent_releaseId = ?", "i", $refReleaseId) as $child) {
                $this->refRelease->childAssocs[$child["name"]] = (object) [
                    "releaseId" => $child["releaseId"],
                    "version" => $child["version"]
                ];
            }
            $this->refRelease->deps = [];
            foreach(Mysql::query("SELECT name, version, depRelId, IF(isHard, 1, 0) required FROM release_deps WHERE releaseId = ?", "i", $refReleaseId) as $row) {
                $row["depRelId"] = (int) $row["depRelId"];
                $row["required"] = (bool) (int) $row["required"];
                $this->refRelease->deps[] = (object) $row;
            }
            $this->refRelease->requires = [];
            foreach(Mysql::query("SELECT type, details, IF(isRequire, 1, 0) isRequire FROM release_reqr WHERE releaseId = ?", "i", $refReleaseId) as $row) {
                $row["type"] = (int) $row["type"];
                $row["isRequire"] = (bool) (int) $row["isRequire"];
                $this->refRelease->requires[] = (object) $row;
            }
        }

        $path = "phar://" . str_replace(DIRECTORY_SEPARATOR, "/", $this->buildInfo->devBuildRsrPath) . "/plugin.yml";
        $data = file_get_contents($path);
        $this->pluginYml = @yaml_parse($data);

        $this->echoHtml($this->getFields());
    }

    private function getFields() {
        $root = Meta::root();

        $fields = [];
        $fields["name"] = [
            "remarks" => <<<EOD
The name of the plugin. This will replace the <code>name</code> attribute in plugin.yml in the release phar, and will be
used in the URL and display name of this release. Therefore, this must not duplicate any other existing plugins.<br/>
The plugin name must not be changed <em>under any circumstances</em> once the first release
EOD
            ,
            "refDefault" => $this->refRelease->name,
            "srcDefault" => $this->pluginYml["name"] ?? null
        ];
        $fields["shortDesc"] = [
            "remarks" => <<<EOD
A one-line brief description of your plugin. One or two <em>simple</em> and <em>attractive</em> sentences describing your
plugin.
EOD
            ,
            "refDefault" => $this->refRelease->shortDesc,
            "srcDefault" => $this->pluginYml["description"] ?? ($this->repoInfo->description ?: null)
        ];
        $fields["version"] = [
            "remarks" => <<<EOD
The version of this release. The version <em>must be named according to <a target="_blank" href="http://semver.org">Semantic Versioning</a></em>,
i.e. the version must consist of two or three numbers, optionally with prerelease information behind a hyphen, e.g.
<code>1.0</code>, <code>2.0.1</code>, <code>3.0.0-beta</code>, <code>4.7.0-beta.3</code>. Note that adding build
metadata behind a <code>+</code> in the version is discouraged due to URL encoding inconvenience.<br/>
This version will replace the <code>version</code> attribute in plugin.yml in the release phar, so this doesn't have to
be same as that in plugin.yml.
EOD
            ,
            "refDefault" => $this->refRelease->version,
            "srcDefault" => $this->pluginYml["version"] ?? null
        ];
        $fields["description"] = [
            "remarks" => <<<EOD
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
Plugins with insufficient or irrelevant description may be rejected.
EOD
            ,
            "refDefault" => $this->refRelease instanceof \stdClass ? [
                "type" => $this->refRelease->desctype === "html" ? "sm" : $this->refRelease->desctype,
                "text" => $this->refRelease->desctype === "html" && $this->refRelease->descrMd !== null ?
                    ResourceManager::read($this->refRelease->descrMd, "md") : ResourceManager::read($this->refRelease->description, $this->refRelease->desctype)
            ] : null,
            "srcDefault" => null
        ];
        $fields["license"] = [
            "remarks" => <<<EOD
The license your plugin is released with.<br/>
You should use the same one used in your source code.
EOD
            ,
            "refDefault" => $this->refRelease instanceof \stdClass ? [
                "type" => $this->refRelease->license,
                "custom" => $this->refRelease->licenseRes === null ? null : ResourceManager::read($this->refRelease->licenseRes, "md")
            ] : null,
            "srcDefault" => [
                "type" => $this->repoInfo->license->key,
                "custom" => null
            ]
        ];
        $fields["preRelease"] = [
            "remarks" => <<<EOD
Pre-release versions will not be listed by default. This is for users to have a "semi-stable" preview version of your
updates.<br/>
Pre-release versions are less likely to be rejected, since a higher amount of bugs are tolerable.
EOD
            ,
            "refDefault" => $this->refRelease instanceof \stdClass ? ($this->refRelease->flags & PluginRelease::RELEASE_FLAG_PRE_RELEASE) > 0 : null,
            "srcDefault" => null
        ];
        $fields["majorCategory"] = [
            "remarks" => <<<EOD
The category of your plugin to be listed in
EOD
            ,
            "refDefault" => $this->refRelease->mainCategory,
            "srcDefault" => null,
        ];
        $fields["minorCategories"] = [
            "remarks" => <<<EOD
Users watching these categories will be notified when you submit or update this plugin.
EOD
            ,
            "refDefault" => $this->refRelease->categories,
            "srcDefault" => null,
        ];
        $fields["keywords"] = [
            "remarks" => <<<EOD
A space-separated list of keywords. Users may search this plugin using keywords. Add some generic keywords, just like
<a tabindex="_blank" href="{$root}gh.topics">Topics in GitHub repositories</a>.
EOD
            ,
            "refDefault" => $this->refRelease instanceof \stdClass ? implode(" ", $this->refRelease->keywords) : null,
            "srcDefault" => implode(" ", $this->repoInfo->topics)
        ];
        $fields["perms"] = [
            "remarks" => <<<EOD
What does this plugin do?
EOD
            ,
            "refDefault" => $this->refRelease->perms,
            "srcDefault" => null,
        ];
        $fields["reqrs"] = [
            "remarks" => <<<EOD
<em>Requirements</em> refer to things that the user <em>must</em> manually setup. This usually refers to external
services used by the plugin, or confidential information that varies on each server.
<em>Enhancements</em> are similar to Requirements, except that they are optional &mdash; the plugin will continue to work
normally even without this manual setup.
EOD
            ,
            "refDefault" => $this->refRelease->requires,
            "srcDefault" => null,
        ];

        if($this->needsChangelog) {
            $lastReleaseLink = Meta::root() . "p/" . $this->lastName . "/" . $this->lastVersion;
            $fields["changelog"] = [
                "remarks" => <<<EOD
List important changes since the <a target="_blank" href="$lastReleaseLink">last release</a> here.<br/>
Make sure you update the description too.
EOD
                ,
                "refDefault" => null,
                "srcDefault" => null
            ];
        }

        $apiVersions = array_keys(PocketMineApi::$VERSIONS);
        $spoonVersions = [];
        foreach($apiVersions as $v) {
            $spoonVersions[$v] = $v;
        }
        $fields["spoons"] = [
            "remarks" => <<<EOD
The PocketMine API versions<a href="{$root}gh.pmmp" target="_blank"><img class='gh-logo' src='{$root}res/ghMark.png' width='12'/></a>
supported by this plugin. This will replace the plugin.yml <code>api</code> attribute. You cannot edit this unless you
submit a new build.<br/>
If you include an API version that your plugin won't work on, this plugin will be rejected.
EOD
            ,
            "refDefault" => $this->refRelease->spoons,
            "srcDefault" => SubmitModule::apisToRanges((array) ($this->pluginYml["api"] ?? [])),
        ];

        $detectedDeps = [];
        foreach((array) ($this->pluginYml["depend"] ?? []) as $name) {
            $detectedDeps[$name] = true;
        }
        foreach((array) ($this->pluginYml["softdepend"] ?? []) as $name) {
            $detectedDeps[$name] = false;
        }
        if(count($detectedDeps) > 0) {
            $qmarks = substr(str_repeat(",?", count($detectedDeps)), 1);
            $rows = Mysql::query("SELECT t.name, r.version, t.releaseId depRelId FROM
                (SELECT name, MAX(releaseId) releaseId FROM releases WHERE state >= ? AND name IN ($qmarks) GROUP BY name) t
                INNER JOIN releases r ON r.releaseId = t.releaseId", "i" . str_repeat("s", count($detectedDeps)), PluginRelease::RELEASE_STATE_SUBMITTED, ...array_keys($detectedDeps));
            foreach($rows as $row) {
                $row["depRelId"] = (int) $row["depRelId"];
                $row["required"] = $detectedDeps[$row["name"]];
                $detectedDeps[$row["name"]] = $row;
            }
            foreach($detectedDeps as $name => $data) {
                if($data === true) $this->errorBadRequest("The plugin requires the dependency \"$name\", but it does not exist");
                if($data === false) unset($detectedDeps[$name]);
            }
        }
        $fields["deps"] = [
            "remarks" => <<<EOD
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
            ,
            "refDefault" => $this->refRelease->deps,
            "srcDefault" => $detectedDeps
        ];

        $detectedAuthors = [];
        $totalChanges = 0;
        $contributors = Curl::ghApiGet("repositories/{$this->repoInfo->id}/contributors", Session::getInstance()->getAccessToken());
        foreach($contributors as $i => $contributor) {
            if(in_array($contributor->id, [ // skip some bots
                8518239, // @gitter-badger
                22427965, // @poggit-bot
                148100, // @invalid-email-address
            ])) {
                unset($contributors[$i]);
                continue;
            }
            $totalChanges += $contributor->contributions;
        }
        foreach($contributors as $contributor) {
            $level = $contributor->contributions / $totalChanges > 0.2 ? PluginRelease::AUTHOR_LEVEL_COLLABORATOR : PluginRelease::AUTHOR_LEVEL_CONTRIBUTOR;
            $detectedAuthors[] = (object) [
                "uid" => $contributor->id,
                "name" => $contributor->login,
                "level" => $level
            ];
        }

        $fields["authors"] = [
            "remarks" => <<<EOD
Producers are people who participated in the development of the plugin. There are four types of producers:
<ol>
    <li>Collaborator: A person who authored or directed a major component of the plugin. This usually refers to people in the plugin
    development team.</li>
    <li>Contributor: A person who contributed minor code changes to the plugin, such as minor bugfixes, small features, etc.</li>
    <li>Translator: A person who contributed to the plugin's non-code assets, such as translating messages, etc.</li>
    <li>Requester: A person who suggested some ideas for the plugin.</li>
</ol>

Do not include organizations here.
Depending on your open-source license, you may or may not need to include all contributors, translators and requesters.<br/>
Depending on the license of the libraries you use, you may or may not need to include their authors above. Poggit does
not recommend including them if not required, but if you are required to, they should be set as contributors.<br/>
If you are updating another person's plugin without permission (and you have made sure you made much enough changes to
submit it as your own plugin here), you <strong>must</strong> add the original author as a collaborator (and the
corresponding contributors and translators too).
EOD
            ,
            "refDefault" => $this->refRelease->authors,
            "srcDefault" => $detectedAuthors
        ];

        // TODO plugin icon
        // TODO assoc

        return $fields;
    }

    private static function apisToRanges(array $input) {
        $versions = array_flip(array_map("strtoupper", array_keys(PocketMineApi::$VERSIONS)));
        $sortedInput = [];
        foreach($input as $api) {
            if(!isset($versions[$api])) {
                continue;
            }
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

    /**
     * @param array[] $fields
     */
    private function echoHtml(array $fields) {
        $minifier = OutputManager::startMinifyHtml();
        ?>
        <html>
        <head prefix="og: http://ogp.me/ns# fb: http://ogp.me/ns/fb# object: http://ogp.me/ns/object# article: http://ogp.me/ns/article# profile: http://ogp.me/ns/profile#">
            <?php $this->headIncludes("Submit Plugin") ?>
            <title>
                <?php switch($this->mode) {
                    case SubmitModule::MODE_SUBMIT:
                        echo "Submit plugin: $this->buildProjectName | Poggit";
                        break;
                    case SubmitModule::MODE_UPDATE:
                        echo "Update $this->lastName from v{$this->lastVersion} | Poggit";
                        break;
                    case SubmitModule::MODE_EDIT:
                        echo "Edit {$this->refRelease->name} v{$this->refRelease->version} | Poggit";
                } ?>
            </title>
            <script>var submitData = <?= json_encode([
                    "repoInfo" => $this->repoInfo,
                    "buildInfo" => $this->buildInfo,
                    "args" => [$this->buildRepoOwner, $this->buildRepoName, $this->buildProjectName, $this->buildNumber],
                    "refRelease" => $this->refRelease,
                    "mode" => $this->mode,
                    "pluginYml" => $this->pluginYml,
                    "fields" => $fields,
                    "last" => isset($this->lastName, $this->lastVersion) ? ["name" => $this->lastName, "version" => $this->lastVersion] : null,
                    "consts" => [
                        "categories" => PluginRelease::$CATEGORIES,
                        "spoons" => PocketMineApi::$VERSIONS,
                        "promotedSpoon" => PocketMineApi::PROMOTED,
                        "perms" => PluginRelease::$PERMISSIONS,
                        "reqrs" => array_flip(PluginRequirement::$NAMES_TO_CONSTANTS),
                        "authors" => PluginRelease::$AUTHOR_TO_HUMAN,
                    ],
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;</script>
        </head>
        <body>
        <?php $this->bodyHeader(); ?>
        <div id="body" class="mainwrapper realsubmitwrapper">
            <div class="submittitle"><h2>Submitting:
                    <a href="<?= Meta::root() . "ci/{$this->buildRepoOwner}/{$this->buildRepoName}/{$this->buildProjectName}" ?>"
                       class="colorless-link"><?= $this->buildInfo->projectName ?> #<?= $this->buildNumber ?>
                        (&amp;<?= dechex($this->buildInfo->buildId) ?>)</a>
                    <?php Mbd::ghLink("https://github.com/{$this->buildRepoOwner}/{$this->buildRepoName}/tree/{$this->buildInfo->sha}/{$this->buildInfo->path}") ?>
                    <img src="<?= Meta::root() ?>ci.badge/<?= "{$this->buildRepoOwner}/{$this->buildRepoName}/{$this->buildProjectName}?build={$this->buildNumber}" ?>"/>
                </h2></div>
            <div class="form-table">
                <noscript>
                    <h1>Please enable JavaScript to submit plugins!</h1>
                </noscript>
            </div>
        </div>
        <?php $this->bodyFooter(); ?>
        <?php $this->includeJs("newSubmit"); ?>
        </body>
        </html>
        <?php
        OutputManager::endMinifyHtml($minifier);
    }
}
