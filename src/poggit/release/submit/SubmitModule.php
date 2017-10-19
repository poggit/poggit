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
use poggit\errdoc\InternalErrorPage;
use poggit\Mbd;
use poggit\Meta;
use poggit\module\AltModuleException;
use poggit\module\Module;
use poggit\release\PluginRequirement;
use poggit\release\Release;
use poggit\release\SubmitException;
use poggit\resource\ResourceManager;
use poggit\utils\internet\Curl;
use poggit\utils\internet\GitHubAPIException;
use poggit\utils\internet\Mysql;
use poggit\utils\lang\Lang;
use poggit\utils\lang\NativeError;
use poggit\utils\OutputManager;
use poggit\utils\PocketMineApi;
use poggit\webhook\WebhookHandler;
use stdClass;

class SubmitModule extends Module {
    const MODE_SUBMIT = "submit";
    const MODE_UPDATE = "update";
    const MODE_EDIT = "edit";

    private $buildRepoOwner;
    private $buildRepoName;
    private $buildProjectName;
    private $buildNumber;
    /** @var stdClass */
    private $repoInfo;
    /** @var stdClass */
    private $buildInfo;
    private $poggitYml;
    private $poggitYmlProject;
    private $pluginYml;
    /** @var stdClass */
    private $refRelease;
    /** @var int */
    private $mode;
    /** @var bool */
    private $needsChangelog;
    /** @var string|void */
    private $lastName;
    /** @var string|void */
    private $lastVersion;
    /** @var string|void */
    private $lastSha;
    /** @var int|void */
    private $lastBuildId;
    /** @var int|void */
    private $lastInternal;

    private $assocParent = null;
    private $assocChildren = [];

    private $iconData;

    public function getName(): string {
        return "submit";
    }

    public function getAllNames(): array {
        return ["submit", "update", "edit"];
    }

    public function output() {
        $this->parseRequestQuery();

        // load repoInfo
        try {
            $this->repoInfo = Curl::ghApiGet("repos/$this->buildRepoOwner/$this->buildRepoName", Session::getInstance()->getAccessToken(), ["Accept: application/vnd.github.drax-preview+json,application/vnd.github.mercy-preview+json"]);
            Curl::clearGhUrls($this->repoInfo, "avatar_url");
            if($this->repoInfo->private) $this->errorBadRequest("Only plugins built from public repos can be submitted");
            $this->buildRepoOwner = $this->repoInfo->owner->login;
            $this->buildRepoName = $this->repoInfo->name;
        } catch(GitHubAPIException $e) {
            $this->errorNotFound();
        }
        // load buildInfo
        try {
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
            if($this->buildInfo->projectType !== ProjectBuilder::PROJECT_TYPE_PLUGIN) $this->errorBadRequest("Only plugin projects can be submitted");
            if($this->buildInfo->devBuildRsr === ResourceManager::NULL_RESOURCE) {
                $this->errorBadRequest("Cannot release $this->buildProjectName dev build #$this->buildNumber because it has a build error");
            }
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
        } catch(\RuntimeException $e) {
            Meta::getLog()->je($e);
            throw new AltModuleException(new InternalErrorPage(""));
        }
        // determine the mode
        if($this->buildInfo->releaseId !== -1) /*edit*/ {
            if(Meta::getModuleName() !== "edit") Meta::redirect("edit/$this->buildRepoOwner/$this->buildRepoName/$this->buildProjectName/$this->buildNumber");
            $this->mode = SubmitModule::MODE_EDIT;
            $refReleaseId = $this->buildInfo->releaseId;
        } elseif($this->buildInfo->lastReleaseId !== -1) /*update*/ {
            if(Meta::getModuleName() !== "update") Meta::redirect("update/$this->buildRepoOwner/$this->buildRepoName/$this->buildProjectName/$this->buildNumber");
            $this->mode = SubmitModule::MODE_UPDATE;
            $refReleaseId = $this->buildInfo->lastReleaseId;
        } else /*submit*/ {
            if(Meta::getModuleName() !== "submit") Meta::redirect("submit/$this->buildRepoOwner/$this->buildRepoName/$this->buildProjectName/$this->buildNumber");
            $this->mode = SubmitModule::MODE_SUBMIT;
            $refReleaseId = null;
        }
        // check repo permission depending on the mode
        if($this->mode !== SubmitModule::MODE_EDIT || Meta::getAdmlv() < Meta::ADMLV_REVIEWER) {
            if(!$this->repoInfo->permissions->{$requiredAccess = $this->mode === SubmitModule::MODE_SUBMIT ? "admin" : "push"}) {
                $this->errorAccessDenied("You must have $requiredAccess access to the repo hosting the plugin to release it");
            }
        }

        $this->loadPoggitYml();

        $this->needsChangelog = false;
        foreach(Mysql::query("SELECT name, releaseId, state, version, builds.buildId, internal, sha
                FROM releases INNER JOIN builds ON releases.buildId = builds.buildId
                WHERE releases.projectId = ? AND releaseId != ? ORDER BY creation ASC",
            "ii", $this->buildInfo->projectId, $this->buildInfo->releaseId) as $row) {
            $state = (int) $row["state"];
            $internal = (int) $row["internal"];
            $releaseLink = Meta::root() . "p/{$row["name"]}/{$row["version"]}";
            if($internal > $this->buildInfo->internal && $state > Release::STATE_REJECTED) {
                $this->errorBadRequest("You have already released <a target='_blank' href='$releaseLink'>v{$row["version"]}</a> based on build #$internal, so you can't make a release from an older build #{$this->buildInfo->internal}", false);
                // FIXME Allow editing old releases
            }
            if($internal === $this->buildInfo->internal) {
                if($state === Release::STATE_REJECTED) {
                    $this->errorBadRequest("You previously tried to release <a target='_blank' href='$releaseLink'>v{$row["version"]}</a> from this build, but it was rejected. If you wish to submit this build again, please delete it first.", false);
                }
            }
            if($state === Release::STATE_SUBMITTED) {
                $this->errorBadRequest("You have previoiusly submitted <a target='_blank' href='$releaseLink'>v{$row["version"]}</a>, which has
                    not been approved yet. Please delete the previous release before releasing new versions", false);
            }
            if($state >= Release::STATE_CHECKED) {
                $this->needsChangelog = true;
                $this->lastName = $row["name"];
                $this->lastVersion = $row["version"];
                $this->lastSha = $row["sha"];
                $this->lastInternal = $internal;
                $this->lastBuildId = (int) $row["buildId"];
            }
        }

        if($refReleaseId === null) {
            $this->refRelease = new class {
                function __get($name) {
//                    if($name === "recursive attribute?") {
//                        return $this;
//                    }
                    return null;
                }
            };
        } else {
            $this->refRelease = (object) Mysql::query("SELECT releaseId, parent_releaseId,
                    name, shortDesc, version, state, buildId, flags, artifact,
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
            $this->refRelease->artifact = (int) $this->refRelease->artifact;
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
        if(!is_array($this->pluginYml)) $this->errorBadRequest("Plugin has corrupted plugin.yml and cannot be submitted!");

        $this->prepareAssocData();
        $this->iconData = $this->loadIcon();

        $submitFormToken = Session::getInstance()->createSubmitFormToken([
            "repoInfo" => $this->repoInfo,
            "buildInfo" => $this->buildInfo,
            "refRelease" => $this->refRelease instanceof stdClass ? $this->refRelease : new stdClass(),
            "lastValidVersion" => $this->needsChangelog ? (object) ["name" => $this->lastName, "version" => $this->lastVersion] : false,
            "mode" => $this->mode,
            "icon" => $this->iconData["url"] ?? false,
        ]);

        $this->echoHtml($this->getFields(), $submitFormToken);
    }

    private function parseRequestQuery() {
        if(!Session::getInstance()->isLoggedIn()) Meta::redirect("login");
        $path = Lang::explodeNoEmpty("/", $this->getQuery(), 4);
        if(count($path) === 0) Meta::redirect("https://youtu.be/SKaOPMT-aM8", true); // TODO write a proper help page
        if(count($path) < 4) Meta::redirect("ci/" . implode("/", $path));
        list($this->buildRepoOwner, $this->buildRepoName, $this->buildProjectName, $this->buildNumber) = $path;
        if($this->buildProjectName === "~") $this->buildProjectName = $this->buildRepoName;
        if(Lang::startsWith(strtolower($this->buildNumber), "dev:")) $this->buildNumber = substr($this->buildNumber, 4);
        if(!is_numeric($this->buildNumber)) Meta::redirect("ci/$this->buildRepoOwner/$this->buildRepoName/$this->buildProjectName");
        $this->buildNumber = (int) $this->buildNumber;
    }

    private function getFields() {
        $root = Meta::root();

        $fields = [];
        $fields["name"] = [
            "remarks" => <<<EOD
The name of the plugin. This will replace the <code>name</code> attribute in plugin.yml in the release phar, and will be
used in the URL and display name of this release. Therefore, this must not duplicate any other existing plugins.<br/>
The plugin name must not be changed <em>under any circumstances</em> after the initial release
EOD
            ,
            "refDefault" => $this->refRelease->name,
            "srcDefault" => $this->pluginYml["name"] ?? null
        ];
        $fields["shortDesc"] = [
            "remarks" => <<<EOD
A brief one-line description of your plugin. One or two <em>simple</em> and <em>attractive</em> sentences describing your
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
            "refDefault" => $this->refRelease instanceof stdClass ? [
                "type" => $this->refRelease->desctype === "html" ? "sm" : $this->refRelease->desctype,
                "text" => $this->refRelease->desctype === "html" && $this->refRelease->descrMd !== null ?
                    ResourceManager::read($this->refRelease->descrMd, "md") :
                    ResourceManager::read($this->refRelease->description, $this->refRelease->desctype)
            ] : null,
            "srcDefault" => null
        ];
        $fields["license"] = [
            "remarks" => <<<EOD
The license under which your plugin is released.<br/>
You should use the same license specified in your source code.
EOD
            ,
            "refDefault" => $this->refRelease instanceof stdClass ? [
                "type" => $this->refRelease->license,
                "custom" => $this->refRelease->licenseRes === null ? null : ResourceManager::read($this->refRelease->licenseRes, "txt")
            ] : null,
            "srcDefault" => [
                "type" => $this->repoInfo->license === null ? "none" : $this->repoInfo->license->key,
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
            "refDefault" => $this->refRelease instanceof stdClass ? ($this->refRelease->flags & Release::FLAG_PRE_RELEASE) > 0 : null,
            "srcDefault" => null
        ];
        $fields["official"] = [
            "remarks" => <<<EOD
Remarks for admin stuff? Nah.
EOD
            ,
            "refDefault" => $this->refRelease instanceof stdClass ? ($this->refRelease->flags & Release::FLAG_OFFICIAL) > 0 : null,
            "srcDefault" => null
        ];
        $fields["outdated"] = [
            "remarks" => <<<EOD
Mark your plugin as <em>Outdated</em> if it is no longer maintained and cannot be used with the latest versions of
PocketMine/MCPE, or if this plugin is no longer useful; e.g. if its functionalities are already provided by PocketMine.
EOD
            ,
            "refDefault" => $this->refRelease instanceof stdClass ? ($this->refRelease->flags & Release::FLAG_OUTDATED) > 0 : null,
            "srcDefault" => null
        ];
        $fields["majorCategory"] = [
            "remarks" => <<<EOD
The category in which your plugin will be listed
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
            "refDefault" => $this->refRelease instanceof stdClass ? implode(" ", $this->refRelease->keywords) : null,
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
<em>Requirements</em> refer to things that the user <em>must</em> set up manually. This usually refers to external
services used by the plugin, or confidential information that varies on each server.
<em>Enhancements</em> are similar to Requirements, except that they are optional &mdash; the plugin will continue to work
normally even without this manual set-up.
EOD
            ,
            "refDefault" => $this->refRelease->requires,
            "srcDefault" => null,
        ];

        if($this->needsChangelog) {
            $lastReleaseLink = Meta::root() . "p/" . $this->lastName . "/" . $this->lastVersion;
            $fields["changelog"] = [
                "remarks" => <<<EOD
List important changes since the <a target="_blank" href="$lastReleaseLink">last release</a> here. Make sure you update the
description too.<br/>
The "Detect" button will load the <em>commit messages</em> since the last release, but <em>using commit messages as the
changelog should be avoided</em>. You should <a href="http://keepachangelog.com/en/1.0.0/#bad-practices" target="_blank">keep
a changelog</a> yourself instead. The "Detect" button is only for your reference, but should not be used as the changelog
directly.
EOD
                ,
                "refDefault" => $this->mode !== SubmitModule::MODE_EDIT || $this->refRelease->changelog === null ? null : [
                    "type" => $this->refRelease->changelogType === "html" ? "gfm" : $this->refRelease->changelogType,
                    "text" => $this->refRelease->changelogType === "html" && $this->refRelease->chlogMd !== null ?
                        ResourceManager::read($this->refRelease->chlogMd, "md") :
                        ResourceManager::read($this->refRelease->changelog, $this->refRelease->changelogType)
                ],
                "srcDefault" => $this->detectChangelog()
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
If you include an API version on which your plugin does not work, this plugin will be rejected.
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
                INNER JOIN releases r ON r.releaseId = t.releaseId", "i" . str_repeat("s", count($detectedDeps)), Release::STATE_SUBMITTED, ...array_keys($detectedDeps));
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
If the required plugin has not yet been submitted (and not rejected) on Poggit Release, you must not submit this plugin.
This plugin will be rejected if the plugins it requires are rejected and have no other available versions.<br/>
Note that dependencies are <em>not</em> plugins you <em>recommend</em> to the user, e.g. do not
add permission plugins as dependencies just because your plugin checks players' permissions. Required dependencies are
plugins whose <em>API or events</em> are used by your plugin, and without which your plugin will <em>crash</em>;
Optional dependencies are plugins whose <em>API or events</em> are used by your plugin, but which
<em>will gracefully skip the related operations and still serve its basic features</em> if not loaded.<br/>
Required and Optional plugins should correspond to the <code>depend</code> and <code>softdepend</code> attributes in plugin.yml.
Poggit will <strong>not</strong> automatically replace such values in plugin.yml, but under normal circumstances, they
should be the same.
EOD
            ,
            "refDefault" => $this->refRelease->deps,
            "srcDefault" => array_values($detectedDeps)
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
            if($contributor->id === $this->repoInfo->owner->id) continue; // repo owner is an implicit collaborator
            $level = $contributor->contributions / $totalChanges > 0.2 ? Release::AUTHOR_LEVEL_COLLABORATOR : Release::AUTHOR_LEVEL_CONTRIBUTOR;
            $detectedAuthors[] = (object) [
                "uid" => $contributor->id,
                "name" => $contributor->login,
                "level" => $level
            ];
        }

        $fields["authors"] = [
            "remarks" => <<<EOD
Producers are people who participated in the development of the plugin. There are four types of producer:
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

        $fields["assocParent"] = [
            "remarks" => <<<EOD
Associate releases are optional "modules" of a large plugin package. For example, for an economy plugin, the one that
manages all the money is the main plugin in the package, but users can also load extra "small plugins" like shop plugin,
land buying plugin, etc.<br/>
On Poggit, the "small plugins" should be submitted as "associate releases" of the "main plugin". The "small plugins"
will not be listed in the plugin list, but they will be downloaded together with the main plugin in a .zip/.tar.gz with
the download button in the main plugin's page. Nevertheless, the "small plugins" can also be downloaded individually.
<br/>
Associate releases must be in repos owned by the same user/organization as the owner of the main plugin's repo.<br/>
If your plugin only uses the API of another plugin but is not one of its components, add it as a <em>Dependency</em>
rather than an <em>Associate release</em>'s parent.
EOD
            ,
            "refDefault" => [
                "name" => $this->assocParent["name"],
                "version" => $this->assocParent["version"],
                "releaseId" => $this->assocParent["releaseId"]
            ],
            "srcDefault" => null
        ];
        $fields["assocChildren"] = [
            "remarks" => <<<EOD
The following versions are linked to the previous version (v{$this->refRelease->version}) as its associate releases. Do
you want to relink them to this version?<br/>
It is suggested that you always relink them unless they are no longer compatible with this version.
EOD
            ,
            "refDefault" => null,
            "srcDefault" => null
        ];

        return $fields;
    }

    public static function apisToRanges(array $input): array {
        $versions = array_flip(array_keys(PocketMineApi::$VERSIONS)); // "1.0.0" => 0, "1.1.0" => 1...
        $sortedInput = []; // "1.0.0" => 0, "1.1.0" => 1...
        foreach($input as $api) {
            $api = strtoupper($api);
            if(!isset($versions[$api])) {
                continue;
            }
            $sortedInput[$api] = $versions[$api];
        }
        asort($sortedInput, SORT_NUMERIC);

        $ranges = [];

        $start = $end = null;
        foreach(PocketMineApi::$VERSIONS as $api => $data) {
            if($start === null) {
                // not selecting (initial state)
                if(isset($sortedInput[$api])) {
                    // start selection
                    $start = $api;
                    $end = $api;
                }
            } else {
                // selection started
                if(!$data["incompatible"]) {
                    // compatible, can extend the selection
                    $end = $api;
                } else {
                    // incompatible, terminate the range if not in input
                    if(!isset($sortedInput[$api])) {
                        $ranges[] = [$start, $end];
                        $start = $end = null;
                    } else {
                        // extend the selection
                        $end = $api;
                    }
                }
            }
        }
        if(isset($start, $end)) $ranges[] = [$start, $end];

        return $ranges;
    }

    public static function rangesToApis(array $input): array {
        $versions = array_keys(PocketMineApi::$VERSIONS); // id => name
        $flatInput = []; // name => id
        foreach($input as list($start, $end)) {
            $startNumber = array_search($start, $versions);
            $endNumber = array_search($end, $versions);
            if($startNumber === false) throw new SubmitException("Unknown API version $start");
            if($endNumber === false) throw new SubmitException("Unknown API version $end");
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
                $output[] = $api;
                $carry = true;
            }
        }

        return $output;
    }

    private function echoHtml(array $fields, string $submitFormToken) {
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
            <script>var submitData = <?= json_encode($outSubmitData = [
                    "repoInfo" => $this->repoInfo,
                    "buildInfo" => $this->buildInfo,
                    "args" => [$this->buildRepoOwner, $this->buildRepoName, $this->buildProjectName, $this->buildNumber],
                    "refRelease" => $this->refRelease,
                    "mode" => $this->mode,
                    "pluginYml" => $this->pluginYml,
                    "fields" => $fields,
                    "consts" => [
                        "categories" => Release::$CATEGORIES,
                        "spoons" => PocketMineApi::$VERSIONS,
                        "promotedSpoon" => PocketMineApi::PROMOTED,
                        "perms" => Release::$PERMISSIONS,
                        "reqrs" => PluginRequirement::$CONST_TO_DETAILS,
                        "authors" => Release::$AUTHOR_TO_HUMAN,
                    ],
                    "assocChildren" => $this->assocChildren,
                    "last" => isset($this->lastName, $this->lastVersion) ? ["name" => $this->lastName, "version" => $this->lastVersion] : null,
                    "submitFormToken" => $submitFormToken,
                    "icon" => $this->iconData
                ], JSON_UNESCAPED_SLASHES | (Meta::$debugIndent ? JSON_PRETTY_PRINT : 0)) ?>; // <?= json_last_error_msg() ?></script>
        </head>
        <body>
        <?php $this->bodyHeader(); ?>
        <div id="body" class="mainwrapper realsubmitwrapper">
            <div class="submittitle"><h2>
                    <?php
                    $projectFullName = "{$this->buildRepoOwner}/{$this->buildRepoName}/{$this->buildProjectName}";
                    $projectPath = Meta::root() . "ci/{$projectFullName}";
                    $linkedProject = "<a href='$projectPath' class='colorless-link' target='_blank'>{$this->buildProjectName}</a>";
                    $linkedBuild = "<a href='{$projectPath}/{$this->buildNumber}' class='colorless-link' target='_blank'>
                            Dev Build #{$this->buildNumber}</a> (&amp;" . dechex($this->buildInfo->buildId) . ")";

                    if($this->mode === SubmitModule::MODE_SUBMIT) {
                        echo "Submitting {$linkedProject} <sub>{$linkedBuild}</sub>";
                    } elseif($this->mode === SubmitModule::MODE_UPDATE) {
                        if(isset($this->lastName)) {
                            echo "Updating {$this->lastName} <sub>{$linkedProject} {$linkedBuild}</sub>";
                        } else {
                            echo "Submitting {$linkedProject} <sub>{$linkedBuild}</sub>";
                        }
                    } elseif($this->mode === SubmitModule::MODE_EDIT) {
                        echo "Editing {$this->refRelease->name} v{$this->refRelease->version}";
                    }
                    ?>
                    <?php
                    $path = WebhookHandler::normalizeProjectPath($this->poggitYmlProject["path"] ?? "");
                    Mbd::ghLink("https://github.com/{$this->buildRepoOwner}/{$this->buildRepoName}/tree/{$this->buildInfo->sha}/$path");
                    ?>
                    <a href="<?= Meta::root() . "ci/{$projectFullName}" ?>" class="colorless-link" target="_blank">
                        <img src="<?= Meta::root() ?>ci.badge/<?= "{$projectFullName}?build={$this->buildNumber}" ?>"/>
                    </a>
                </h2></div>
            <div class="submitintro">
                <?php if(isset($this->lastName)) { ?>
                    <h5>Updates v<?= $this->lastVersion ?><sub>
                            <a href="<?= $projectPath ?>/<?= $this->lastInternal ?>" class="colorless-link"
                               target="_blank">Dev
                                Build #<?= $this->lastInternal ?> (&amp;<?= dechex($this->lastBuildId) ?>)</a></sub>
                    </h5>
                <?php } ?>
                <p class="remark">Your plugin will be reviewed by Poggit reviewers according to <a
                            href="<?= Meta::root() ?>" target="_blank">PQRS</a>.</p>
                <p class="remark"><strong>Do no submit plugins written by other people. Your access to Poggit may be
                        blocked
                        if you do so.</strong> If you want them to be available on Poggit, please request it at the
                    <a href="https://github.com/poggit-orphanage/office/issues" target="_blank">Poggit Orphanage
                        Office</a>.
                    <br/>
                    If you only rewrote the plugin but did not take any code from the original author, consider using a
                    new
                    plugin name, or at least add something like <code>_New</code> behind the plugin name. Consider
                    adding
                    the original author as a <em>Requester</em> in the <em>Producers</em> field below.<br/>
                    If you have used some code from the original author but have made major changes to the plugin, you
                    are
                    allowed to submit this plugin from your <em>fork</em> repo, but you <strong>must</strong> add the
                    original author as a <em>collaborator</em> in the <em>Producers</em> field below.
                </p>
                <p class="remark">Note: If you don't submit this form within three hours after loading this page, this
                    form
                    will become invalid and you will have to reload this page.</p>
            </div>
            <div class="form-table">
                <h3>Loading...</h3>
                <p>If this page doesn't load in a few seconds, try refreshing the page. You must enable JavaScript to
                    use
                    this page.</p>
            </div>
        </div>
        <?php $this->bodyFooter(); ?>
        <?php $this->includeJs("newSubmit"); ?>
        </body>
        </html>
        <?php
        OutputManager::endMinifyHtml($minifier);
    }

    private function detectChangelog() {
        $messages = [];
        foreach(Curl::ghApiGet("repositories/{$this->repoInfo->id}/commits?sha={$this->buildInfo->sha}&path=" . urlencode($this->buildInfo->path), Session::getInstance()->getAccessToken(), ["Accept: application/vnd.github.v3+json"], false, function($data) {
            foreach($data as $datum) {
                if($datum->sha === $this->lastSha) return false;
            }
            return true;
        }) as $commit) {
            if($commit->sha === $this->lastSha) break;
            if(Lang::startsWith(strtolower($commit->commit->message), "merge branch ")) continue;
            $messages[] = $commit->commit->message;
        }
        $md = "";
        $messages = array_unique($messages, SORT_STRING);
        sort($messages, SORT_STRING);
        foreach($messages as $message) {
            $lines = Lang::explodeNoEmpty("\n", $message);
            $md .= "* " . trim($lines[0]) . "\n";
            for($i = 1; $i < count($lines); ++$i) {
                $md .= "  * " . trim($lines[$i]) . "\n";
            }
        }
        return [
            "type" => "gfm",
            "text" => $md
        ];
    }

    private function prepareAssocData() {
        if($this->mode === self::MODE_UPDATE) {
            foreach(Mysql::query("SELECT releaseId, name, version FROM releases WHERE parent_releaseId = ? AND state >= ?",
                "ii", $this->refRelease->releaseId, Release::STATE_SUBMITTED) as $row) {
                $this->assocChildren[(int) $row["releaseId"]] = $child = new stdClass();
                $child->name = $row["name"];
                $child->description = $row["version"];
            }
        }
    }

    private function loadPoggitYml() {
        try {
            $response = Curl::ghApiGet("repositories/{$this->repoInfo->id}/contents/.poggit.yml?ref=" . $this->buildInfo->sha, Session::getInstance()
                ->getAccessToken(), ["Accept: application/vnd.github.VERSION.raw"], true);
        } catch(GitHubAPIException $e) {
            try {
                $response = Curl::ghApiGet("repositories/{$this->repoInfo->id}/contents/.poggit/.poggit.yml?ref=" . $this->buildInfo->sha, Session::getInstance()
                    ->getAccessToken(), ["Accept: application/vnd.github.VERSION.raw"],
                    true);
            } catch(GitHubAPIException $e) {
                Meta::getLog()->wtf(".poggit.yml missing");
                Meta::getLog()->jwtf($e);
                $this->errorBadRequest(".poggit.yml missing in submitted plugin");
                return;
            }
        }
        try {
            $data = yaml_parse($response);
            if(!is_array($data)) {
                throw new \Exception("Error parsing .poggit.yml");
            }
        } catch(NativeError $e) {
                Meta::getLog()->wtf("Error parsing .poggit.yml of submitted plugin");
            Meta::getLog()->jwtf($e);
            $this->errorBadRequest("Error parsing .poggit.yml");
            return;
        }

        $this->poggitYml = $data;
        $this->poggitYmlProject = array_change_key_case($data["projects"], CASE_LOWER)[strtolower($this->buildProjectName)] ?? null;
        if(!is_array($this->poggitYmlProject)) {
            $this->errorBadRequest("Cannot find the project $this->buildProjectName from the .poggit.yml back then. Was the project renamed? Please contact a Poggit admin if you need help."); // FIXME
        }
    }

    private function loadIcon() {
        $iconPath = $this->poggitYmlProject["icon"] ?? "icon.png";
        $iconPath = $iconPath{0} === "/" ? substr($iconPath, 1) : $this->buildInfo->path . $iconPath;

        $ADD_ICON_INSTRUCTIONS = <<<INSTR
<p>To add an icon for your plugin:</p>
<ol start="0">
<li>You will have to submit the plugin from another build. To keep the changes in this page, click "Save as Draft" and
close this page.</li>
<li>Add the icon file into the project directory (next to plugin.yml). Give it any name you like.</li>
<li>In .poggit.yml, under this project's node (next to attributes like <code>path</code>), add a property
<code>icon</code> with the icon file's path relative to the project directory (i.e. the file name) as the value.
<ul><li>Make sure there are no leading slashes; leading slashes imply that the path is relative to the repo root rather
than the project directory.</li></ul></li>
<li>Add, commit and push the changes to GitHub. Make sure the commit triggered a new Poggit-CI build for the project.
</li>
<li>Submit this plugin from the new build.</li>
</ol>
<p>If you don't want to modify .poggit.yml, you may name the icon file as icon.png and add it in the project directory
directly. This will prevent triggering builds for other projects in the repo.</p>
INSTR;

        $escapedIconPath = htmlspecialchars($iconPath);

        try {
            $response = Curl::ghApiGet("repositories/{$this->repoInfo->id}/contents/$iconPath?ref=" . $this->buildInfo->sha, Session::getInstance()->getAccessToken());
        } catch(GitHubAPIException $e) {
            if(isset($this->poggitYmlProject["icon"])) {
                $html = <<<EOM
<p>.poggit.yml declares an icon at <code>$escapedIconPath</code>, but there is no such file in your repo! The default
icon will be used; your plugin will not be considered for featuring without a custom icon.</p>
$ADD_ICON_INSTRUCTIONS
EOM;
            } else {
                $html = <<<EOM
<p>This build does not contain any icon data. The default icon will be used; your plugin will not be considered for
featuring without a custom icon.</p>
$ADD_ICON_INSTRUCTIONS
EOM;
            }
            return ["url" => null, "html" => $html];
        }

        $invalidFile = ["url" => null, "html" => <<<EOM
<p>The icon file at <code>$escapedIconPath</code> is not a valid image file; the default icon will be used. Please
review the instructions for adding an icon; your plugin will not be considered for featuring without a valid custom
icon.</p>
$ADD_ICON_INSTRUCTIONS
EOM
        ];

        if(!is_object($response) || $response->type !== "file" || $response->encoding !== "base64") return $this->iconData = $invalidFile;

        $imageString = base64_decode($response->content);

        if(strlen($imageString) > (256 << 10)) {
            $size = strlen($imageString) / (1 << 10);
            return ["url" => null, "html" => <<<EOM
<p>The icon file at <code>$escapedIconPath</code> is too large ($size KB). The size of icon files must not exceed 256
KB, and its dimensions must not exceed 256&times;256 px. As a result, the default icon will be used. Please review the
instructions for adding an icon; your plugin will not be considered for featuring without a valid custom icon.</p>
$ADD_ICON_INSTRUCTIONS
EOM
            ];
        }

        // getImageSizeFromString may return false-true values.
        try {
            if(imagecreatefromstring($imageString) === false) throw new \Exception;
        } catch(\Exception $e) {
            return ["url" => null, "html" => <<<EOM
<p>The icon file at <code>$escapedIconPath</code> is not a valid image file; the default icon will be used. Please
review the instructions for adding an icon; your plugin will not be considered for featuring without a valid custom
icon.</p>
$ADD_ICON_INSTRUCTIONS
EOM
            ];
        }

        list($width, $height, $type) = $imageData = getimagesizefromstring($imageString);
        if($width > 256 || $height > 256) {
            return ["url" => null, "html" => <<<EOM
<p>The icon file at <code>$escapedIconPath</code> is too large ($width&times;$height px), exceeding the limit (256&times;256
px); the default icon will be used. Please review the instructions for adding an icon; your plugin will not be
considered for featuring without a valid custom icon.</p>
$ADD_ICON_INSTRUCTIONS
EOM
            ];
        }
        $escapedMime = htmlspecialchars($imageData["mime"]);
        if($type !== IMAGETYPE_GIF && $type !== IMAGETYPE_JPEG && $type !== IMAGETYPE_PNG && $type !== IMAGETYPE_ICO) {
            return ["url" => null, "html" => <<<EOM
<p>The image type of icon file at <code>$escapedIconPath</code> is <code>$escapedMime</code>, which is not allowed; the
default icon will be used. Please review the instructions for adding an icon; your plugin will not be
considered for featuring without a valid custom icon.</p>
$ADD_ICON_INSTRUCTIONS
EOM
            ];
        }
        $sizeStr = Lang::formatFileSize(strlen($imageString));
        return ["url" => $response->download_url, "html" => <<<EOM
<p>Using image from <code>$iconPath</code><br/>
Type: <code>$escapedMime</code><br/>
Dimensions: $width&times;$height px<br/>
Size: $sizeStr</p>
EOM
        ];
    }
}
