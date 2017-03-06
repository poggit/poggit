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

use poggit\account\SessionUtils;
use poggit\ci\lint\BuildResult;
use poggit\Mbd;
use poggit\module\VarPage;
use poggit\Poggit;
use poggit\release\PluginRelease;
use poggit\resource\ResourceManager;
use poggit\utils\internet\CurlUtils;
use poggit\utils\internet\GitHubAPIException;
use poggit\utils\PocketMineApi;

class RealSubmitPage extends VarPage {
    /** @var SubmitPluginModule */
    private $module;
    private $mainAction;
    private $isRelease;
    private $hasRelease;
    private $licenseDisplayStyle;
    private $licenseText;
    private $keywords;
    private $categories;
    private $mainCategory;
    private $spoons;
    private $permissions;
    private $deps;
    private $reqr;
    private $descType;
    private $changelogText;
    private $changelogType;

    public function __construct(SubmitPluginModule $module) {
        $this->module = $module;
        $this->hasRelease = $module->lastRelease !== [];
        $this->isRelease = ($this->hasRelease && ($module->buildInfo["buildId"] == $module->lastRelease["buildId"])) ?? false;
        $this->mainAction = ($this->isRelease) ? "Editing Plugin" : "Releasing Plugin";
        $this->licenseDisplayStyle = ($this->hasRelease && $this->module->lastRelease["license"] == "custom") ? "display: true" : "display: none";
        $this->licenseText = ($this->hasRelease && $this->module->lastRelease["licenseRes"]) ? file_get_contents(ResourceManager::getInstance()->getResource($this->module->lastRelease["licenseRes"])) : "";
        $this->changelogText = ($this->hasRelease && $this->module->lastRelease["changelog"]) ? file_get_contents(ResourceManager::getInstance()->getResource($this->module->lastRelease["changelog"])) : "";
        $this->changelogType = ($this->hasRelease && $this->module->lastRelease["changelogType"]) ? $this->module->lastRelease["changelogType"] : "md";
        $this->keywords = ($this->hasRelease && $this->module->lastRelease["keywords"]) ? implode(" ", $this->module->lastRelease["keywords"]) : "";
        $this->categories = ($this->hasRelease && $this->module->lastRelease["categories"]) ? $this->module->lastRelease["categories"] : [];
        $this->spoons = ($this->hasRelease && $this->module->lastRelease["spoons"]) ? $this->module->lastRelease["spoons"] : [];
        $this->permissions = ($this->hasRelease && $this->module->lastRelease["permissions"]) ? $this->module->lastRelease["permissions"] : [];
        $this->deps = ($this->hasRelease && $this->module->lastRelease["deps"]) ? $this->module->lastRelease["deps"] : [];
        $this->reqr = ($this->hasRelease && $this->module->lastRelease["reqr"]) ? $this->module->lastRelease["reqr"] : [];
        $this->mainCategory = ($this->hasRelease && $this->module->lastRelease["maincategory"]) ? $this->module->lastRelease["maincategory"] : 1;
        $this->descType = ($this->hasRelease && $this->module->lastRelease["desctype"]) ? $this->module->lastRelease["desctype"] : "md";
    }

    public function getTitle(): string {
        return $this->mainAction . ": " . $this->module->owner . "/" . $this->module->repo . "/" . $this->module->project;
    }

    public function getProjectUrl(): string {
        return Poggit::getRootPath() . "ci/" . $this->module->owner . "/" . $this->module->repo . "/" . $this->module->project;
    }

    public function output() {
        $buildPath = Poggit::getRootPath() . "ci/{$this->module->owner}/{$this->module->repo}/{$this->module->project}/dev:{$this->module->build}";
        $token = SessionUtils::getInstance()->getAccessToken();
        try {
            $manifestContent = CurlUtils::ghApiGet("repos/{$this->module->owner}/{$this->module->repo}/contents/.poggit.yml", $token);
        } catch(GitHubAPIException $e) {
            try {
                $manifestContent = CurlUtils::ghApiGet("repos/{$this->module->owner}/{$this->module->repo}/contents/.poggit/.poggit.yml", $token);
            } catch(GitHubAPIException $e) {
                if(isset($manifest)) unset($manifestContent);
            }
        }
        if(isset($manifestContent)) {
            $manifestRaw = base64_decode($manifestContent->content);
            $manifest = yaml_parse($manifestRaw);
        }
        $manifest = (isset($manifest) and is_array($manifest)) ? (object) $manifest["projects"][$this->module->project] : new \stdClass();

        $icon = PluginRelease::findIcon($this->module->owner . "/" . $this->module->repo, $this->module->projectDetails["path"] . ($manifest->icon ?? "icon.png"), $this->module->buildInfo["branch"] ?? $this->module->repo, $token);

        ?>
        <!--suppress JSUnusedLocalSymbols -->
        <script>
            var pluginSubmitData = {
                owner: <?= json_encode($this->module->owner, JSON_UNESCAPED_SLASHES) ?>,
                repo: <?= json_encode($this->module->repo, JSON_UNESCAPED_SLASHES) ?>,
                project: <?= json_encode($this->module->project, JSON_UNESCAPED_SLASHES) ?>,
                build: <?= json_encode($this->module->build, JSON_UNESCAPED_SLASHES) ?>,
                projectDetails: <?= json_encode($this->module->projectDetails, JSON_UNESCAPED_SLASHES) ?>,
                lastRelease: <?= json_encode($this->module->lastRelease === [] ? null : $this->module->lastRelease, JSON_UNESCAPED_SLASHES) ?>,
                buildInfo: <?= json_encode($this->module->buildInfo, JSON_UNESCAPED_SLASHES) ?>,
                iconName: <?= json_encode($icon->name ?? null, JSON_UNESCAPED_SLASHES) ?>,
                spoonCount: <?= count($this->spoons)?>
            };
        </script>
        <div class="realsubmitwrapper">
            <div class="submittitle"><h2><?= $this->mainAction . ": " . $this->module->project ?></h2></div>
            <p>Project / Build: <a href="<?= $this->getProjectUrl() ?>"><?= $this->module->project ?></a> / <a
                        href="<?= $buildPath ?>" target="_blank">Build #<?= $this->module->build ?>
                    &amp;<?= dechex($this->module->buildInfo["buildId"]) ?></a>
                <?php if(count($this->module->buildInfo["statusCount"]) > 0) {
                    static $levels = [
                        BuildResult::LEVEL_BUILD_ERROR => "Build Error",
                        BuildResult::LEVEL_ERROR => "Error",
                        BuildResult::LEVEL_WARN => "Warning",
                        BuildResult::LEVEL_LINT => "Lint",
                    ];
                    $parts = [];
                    foreach($this->module->buildInfo["statusCount"] as $statusCounts) {
                        $parts[] = $statusCounts["cnt"] . " " . $levels[$statusCounts["level"]] . ($statusCounts["cnt"] > 1 ? "s" : "");
                    }
                    echo "(" . implode(", ", $parts) . ")";
                } ?>
            </p>
            <p class="verbose">Poggit Reviewers will review the plugins according to the latest version of
                <a href="<?= Poggit::getRootPath() ?>pqrs">PQRS</a>, as well as other common-sense criteria.</p>
            <p class="verbose">Do <strong>not</strong> submit plugins written by other people, unless you have obtained
                prior explicit
                permission from them. If you want an updated plugin to be listed on Poggit, request it at the
                <a href="https://github.com/poggit-orphanage/office/issues/new">Poggit Orphanage Office</a>.</p>
            <p class="verbose">Poggit Reviewers may leave comments on this release in the GitHub page for the commit for
                this build
                <?php Mbd::ghLink("https://github.com/{$this->module->owner}/{$this->module->repo}/commit/" .
                    $this->module->buildInfo["sha"]) ?>.
                Please do not lock the conversation in the page, or you may be unable to receive reviews.
            </p>
            <div class="form-table">
                <div class="form-row">
                    <div class="form-key">Plugin name</div>
                    <div class="form-value">
                        <input id="submit-pluginName" type="text" size="32"
                               value="<?= $this->module->lastRelease["name"] ?? $this->module->project ?>"
                            <?= $this->hasRelease ? "disabled" :
                                'autofocus onblur="checkPluginName();"' ?>
                        />
                        <span class="explain" id="submit-afterPluginName" style="font-weight: bold;"></span>
                        <span class="explain">Name of the plugin to be displayed. This can be different from the
                                project name, and it must not already exist.</span></div>
                </div>
                <div class="form-row">
                    <div class="form-key">Tagline</div>
                    <div class="form-value">
                        <input type="text" size="64" maxlength="128" id="submit-shortDesc"
                               value="<?= $this->module->lastRelease["shortDesc"] ?? $manifest->tagline ?? $manifest->shortDesc ?? "" ?>"/><br/>
                        <span class="explain">One-line text describing the plugin, shown directly below the plugin name
                        in the plugin list. Make good use of this line to attract users' attention.</span>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-key">Version name</div>
                    <div class="form-value">
                        <input value="<?= ($this->isRelease && $this->module->existingVersionName) ? $this->module->existingVersionName : "" ?>"
                               type="text" id="submit-version" size="10" maxlength="16"/><br/>
                        <span class="explain">Unique version name of this plugin release<br/>
                            This version name will <strong>replace the version in plugin.yml</strong>. This will
                            overwrite the version you used in the source code. Make sure you are providing the
                            correct version name.</span>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-key">Plugin Description</div>
                    <div class="form-value">
                        <!-- TODO inherit from last release -->
                        <!-- TODO populate from manifest -->
                        <textarea name="pluginDesc" id="submit-pluginDescTextArea" cols="72"
                                  rows="10"></textarea><br/>
                        Format: <select id="submit-pluginDescTypeSelect">
                            <option value="md" <?= $this->descType == "md" ? "selected" : "" ?>>GH Markdown (context:
                                github.com/<?= $this->module->owner ?>/<?= $this->module->repo ?>)
                            </option>
                            <option value="txt" <?= $this->descType == "txt" ? "selected" : "" ?>>Plain text</option>
                        </select><br/>
                        <div id="possibleDescriptionImports"></div>
                        <br/>
                        <span class="explain">Brief explanation of your plugin. You should include
                                <strong>all</strong> features provided by your plugin here so that reviewers won't be
                                confused by the code you write.</span>
                    </div>
                </div>
                <?php if($this->hasRelease) { ?>
                    <div class="form-row">
                        <div class="form-key">What's new</div>
                        <!-- TODO populate from manifest -->
                        <div class="form-value">
                            <textarea id="submit-pluginChangeLogTextArea" cols="72"
                                      rows="10"><?= $this->changelogText ?></textarea><br/>
                            Format: <select id="submit-pluginChangeLogTypeSelect">
                                <option value="md" <?= $this->changelogType === "md" ? "selected" : "" ?>>GH Markdown
                                    (context:
                                    github.com/<?= $this->module->owner ?>/<?= $this->module->repo ?>)
                                </option>
                                <option value="txt" <?= $this->changelogType === "txt" ? "selected" : "" ?>>Plain text
                                </option>
                            </select><br/>
                            <span class="explain">Briefly point out what is new in this update.
                            This information is used by plugin reviewers.</span>
                        </div>
                    </div>
                <?php } ?>
                <div class="form-row">
                    <div class="form-key">License</div>
                    <div class="form-value">
                        <div class="explain">
                            <p>Choose a license to be displayed in the plugin page. The templates provided by GitHub
                                will be used. Poggit will not try to fetch the license from your GitHub repo. You may
                                also put a custom license here.</p>
                            <p>Also note that Poggit is not a legal firm. Please do not rely on Poggit for legal license
                                information.</p>
                        </div>
                        <select id="submit-chooseLicense">
                            <option value="none" selected>No license</option>
                            <option value="custom">Custom license</option>
                        </select>
                        <span class="action disabled" id="viewLicenseDetails">View license details</span><br/>
                        <textarea id="submit-customLicense" style="<?= $this->licenseDisplayStyle ?>"
                                  placeholder="Custom license content"
                                  rows="30"><?= htmlentities($this->licenseText) ?></textarea>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-key">
                        <nobr>Pre-release</nobr>
                    </div>
                    <div class="form-value">
                        <input type="checkbox"
                               id="submit-isPreRelease" <?= ($this->isRelease && ($this->module->lastRelease["flags"] == PluginRelease::RELEASE_FLAG_PRE_RELEASE)) ? "checked" : "" ?>><br/>
                        <span class="explain">A pre-release is a preview of a release of your plugin. It must still
                                be functional even if some features are not completed, and you must emphasize this
                                in the description. Pre-releases can be buggy or unstable, but not too much or they
                                will not be approved.
                        </span>
                    </div>
                </div>
                <!-- TODO inherit from previous release, and disable if inherited? -->
                <!-- TODO populate from manifest -->
                <div class="form-row">
                    <div class="form-key">Categories</div>
                    <div class="form-value">
                        Major category: <select id="submit-majorCategory">
                            <?php
                            foreach(PluginRelease::$CATEGORIES as $id => $name) {
                                $selected = $id === $this->mainCategory ? "selected" : "";
                                echo "<option value='$id' $selected>" . htmlspecialchars($name) . "</option>";
                            }
                            ?>
                        </select><br/>
                        Minor categories:
                        <div class="submitReleaseCats" id="submit-minorCats">
                            <?php
                            foreach(PluginRelease::$CATEGORIES as $id => $name) {
                                $checked = in_array($id, $this->categories) ? "checked" : "";
                                echo "<div class='cbinput'><input class='minorCat' type='checkbox' $checked value='$id'>" .
                                    htmlspecialchars($name) . "</input></div>";
                            }
                            ?>
                        </div>
                        <p class="explain">This plugin will be listed in the major category, but users subscribing
                            to
                            the minor categories will also be notified when this plugin is released.<br/>
                            You do not need to select the major category in minor categories</p>
                    </div>
                </div>
                <!-- TODO inherit from previous release -->
                <!-- TODO populate from manifest -->
                <div class="form-row">
                    <div class="form-key">Keywords</div>
                    <div class="form-value">
                        <input type="text" id="submit-keywords" value="<?= $this->keywords ?>">
                        <p class="explain">Separate different keywords with spaces. These keywords will be used to let
                            users search plugins. Synonyms are allowed, but use no more than
                            <?= PluginRelease::MAX_KEYWORD_COUNT ?> keywords.<br/>
                            Use of bare form words and short forms (such as <em>auth</em> instead of
                            <em>authentication</em>, <em>stat</em> instead of <em>statistics</em>, <em>chest</em>
                            instead of <em>chests</em>, etc., are recommended.</p>
                    </div>
                </div>
                <!-- TODO inherit from previous release -->
                <!-- TODO populate from manifest -->
                <div class="form-row">
                    <div class="form-key">Supported API versions</div>
                    <div class="form-value">
                        <script>
                            var pocketMineApiVersions = <?= json_encode(PocketMineApi::$VERSIONS, JSON_UNESCAPED_SLASHES) ?>;
                        </script>
                        <span class="explain">The PocketMine <?php Mbd::ghLink("https://github.com/pmmp/PocketMine-MP") ?>
                            <em>API versions</em> supported by this plugin.<br/>
                            Please note that Poggit only accepts submission of plugins written and tested on PocketMine.
                            Plugins written for PocketMine variants are <strong>not</strong> accepted.
                        </span>
                        <table class="info-table" id="supportedSpoonsValue">
                            <colgroup span="3"></colgroup>
                            <tr>
                                <th colspan="3" scope="colgroup"><em>API</em> Version</th>
                            </tr>
                            <tr id="submit-spoonEntry" class="submit-spoonEntry" style="display: none;">
                                <td>
                                    <select class="submit-spoonVersion-from">
                                        <?php foreach(PocketMineApi::$VERSIONS as $version => $majors) { ?>
                                            <option <?= $version === PocketMineApi::PROMOTED ? "selected" : "" ?>
                                                    value="<?= $version ?>"><?= $version ?></option>
                                        <?php } ?>
                                    </select>
                                </td>
                                <td style="border:none;">-</td>
                                <td>
                                    <select class="submit-spoonVersion-to">
                                        <?php foreach(array_keys(PocketMineApi::$VERSIONS) as $i => $version) { ?>
                                            <option <?= $i + 1 === count(PocketMineApi::$VERSIONS) ? "selected" : "" ?>
                                                    value="<?= $version ?>"><?= $version ?></option>
                                        <?php } ?>
                                    </select>
                                </td>
                                <td style="border:none;"><span class="action deleteSpoonRow"
                                                               onclick="deleteRowFromListInfoTable(this);">X
                                    </span></td>
                            </tr>
                            <?php if(count($this->spoons) > 0) {
                                foreach($this->spoons["since"] as $key => $since) { ?>
                                    <tr class="submit-spoonEntry">
                                        <td>
                                            <select class="submit-spoonVersion-from">
                                                <?php foreach(PocketMineApi::$VERSIONS as $version => $majors) { ?>
                                                    <option <?= $version == $since ? "selected" : "" ?>
                                                            value="<?= $version ?>"><?= $version ?></option>
                                                <?php } ?>
                                            </select>
                                        </td>
                                        <td style="border:none;">-</td>
                                        <td>
                                            <select class="submit-spoonVersion-to">
                                                <?php foreach(PocketMineApi::$VERSIONS as $version => $majors) { ?>
                                                    <option <?= ($version == $this->spoons["till"][$key]) ? "selected" : "" ?>
                                                            value="<?= $version ?>"><?= $version ?></option>
                                                <?php } ?>
                                            </select>
                                        </td>
                                        <td style="border:none;"><span class="action deleteSpoonRow"
                                                                       onclick="deleteRowFromListInfoTable(this);">X
                                </span></td>
                                    </tr>
                                <?php }
                            } ?>
                        </table>
                        <span onclick='addRowToListInfoTable("submit-spoonEntry", "supportedSpoonsValue");'
                              class="action">Add row</span>
                    </div>
                </div>
                <!-- TODO inherit from previous release -->
                <!-- TODO populate from manifest -->
                <div class="form-row">
                    <div class="form-key">Dependencies</div>
                    <div class="form-value">
                        <table class="info-table table-bordered" id="dependenciesValue">
                            <tr>
                                <th>Plugin Name</th>
                                <th>Poggit Release</th>
                                <th>Required / Optional</th>
                            </tr>
                            <tr id="baseDepForm" class="submit-depEntry" style="display: none;">
                                <td>
                                    <div class="dep-select-inline">
                                        <input type="text" class="submit-depName"
                                               value=""/>
                                        <button type="button"
                                                class="submit-depRelIdTrigger btn btn-primary btn-sm text-center"
                                                onclick='searchDep($(this).parents("tr"))'>Search Plugins
                                        </button>
                                    </div>
                                </td>
                                <td>
                                    <select id="submit-depSelect">
                                        <option releaseId="0">No Results</option>
                                    </select>
                                </td>
                                <td>
                                    <select class="submit-depSoftness">
                                        <option value="hard">Required</option>
                                        <option value="soft">Optional</option>
                                    </select>
                                </td>
                                <td style="border:none;"><span class="action deleteDepRow"
                                                               onclick="deleteRowFromListInfoTable(this)">X</span>
                                </td>
                            </tr>
                            <?php if(count($this->deps) > 0) {
                                foreach($this->deps["name"] as $key => $name) { ?>
                                    <tr class="submit-depEntry">
                                        <td>
                                            <div class="dep-select-inline">
                                                <input type="text" class="submit-depName"
                                                       value="<?= $name ?>"/>
                                                <button type="button"
                                                        class="submit-depRelIdTrigger btn btn-primary btn-sm text-center"
                                                        onclick='searchDep($(this).parents("tr"))'>Search Plugins
                                                </button>
                                            </div>
                                        </td>
                                        <td>
                                            <select id="submit-depSelect">
                                                 <option name="<?= $name ?>" releaseId="<?= $this->deps["depRelId"][$key] ?>"><?= $name ?> <?= $this->deps["version"][$key] ?></option>
                                            </select>
                                        </td>
                                        <td>
                                            <select class="submit-depSoftness">
                                                <option value="hard" <?= $this->deps["isHard"][$key] == 1 ? "selected" : "" ?>>
                                                    Required
                                                </option>
                                                <option value="soft" <?= $this->deps["isHard"][$key] == 0 ? "selected" : "" ?>>
                                                    Optional
                                                </option>
                                            </select>
                                        </td>
                                        <td style="border:none;"><span class="action deleteDepRow"
                                                                       onclick="deleteRowFromListInfoTable(this)">X</span>
                                        </td>
                                    </tr>
                                <?php }
                            } ?>
                        </table>
                        <span onclick='addRowToListInfoTable("baseDepForm", "dependenciesValue");'
                              class="action">Add row</span>
                        <span class="explain">Other plugins that this plugin requires, or works
                            with (optionally). We recommend putting the latest version of the other plugin that has been tested
                            with your plugin, but you don't need to update this value if new compatible versions of the
                            other plugin are released.
                        </span>
                    </div>
                </div>
                <!-- TODO inherit from previous release -->
                <!-- TODO populate from manifest -->
                <div class="form-row">
                    <div class="form-key">Permissions</div>
                    <div class="form-value">
                        <span class="explain">Server actions for which this plugin requires permissions</span>
                        <div id="submit-perms" class="submit-perms-wrapper">
                            <?php foreach(PluginRelease::$PERMISSIONS as $value => list($perm, $reason)) { ?>
                                <div class="submit-perms-row">
                                    <div class="cbinput">
                                        <input type="checkbox" class="submit-permEntry"
                                               value="<?= $value ?>" <?= in_array($value, $this->permissions) ? "checked" : "" ?>/>
                                        <?= htmlspecialchars($perm) ?>
                                    </div>
                                    <div class="remark"><?= htmlspecialchars($reason) ?></div>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>

                <!-- TODO inherit from previous release -->
                <!-- TODO populate from manifest -->
                <div class="form-row">
                    <div class="form-key">Requirements/<br/>Enhancements</div>
                    <div class="form-value">
                        <p class="explain">Requirements and Enhancements are external processes run on the server, or
                            information for which you cannot provide a default value
                            in the config file because it may vary for each user. In other words: things that must be
                            installed or setup manually when the user
                            installs the plugin.<br/>
                            For example, if your plugin uses mail, a mail server has to be installed first.<br/>
                            Another example is that if your plugin uses the external API of a website like the GitHub
                            API, your plugin would require the API token from the user. The user must manually enter
                            this information to the plugin after installing.<br/>
                            <strong>Requirements</strong> are <em>mandatory</em> for the plugin, i.e. if you don't set
                            the required values, the plugin won't work.<br/>
                            <strong>Enhancements</strong> are <em>optional</em>. The plugin can still start and work
                            properly without the values set, but some optional features won't be enabled.
                        </p>
                        <div id="submit-req">
                            <table class="info-table" id="reqrValue">
                                <tr>
                                    <th>Type</th>
                                    <th>Details</th>
                                    <th>Required?</th>
                                </tr>
                                <tr id="baseReqrForm" class="submit-reqrEntry" style="display: none;">
                                    <td>
                                        <select class="submit-reqrType">
                                            <option value="mail">Mail server (please specify type type of mail server
                                                required)
                                            </option>
                                            <option value="mysql">MySQL database</option>
                                            <option value="apiToken">Service API token (please specify what service)
                                            </option>
                                            <option value="password">Passwords for services provided by the plugin
                                            </option>
                                            <option value="other">Other (please specify)</option>
                                        </select>
                                    </td>
                                    <td><input type="text" class="submit-reqrSpec"/></td>
                                    <td>
                                        <select class="submit-reqrEnhc">
                                            <option value="requirement">Requirement</option>
                                            <option value="enhancement">Enhancement</option>
                                        </select>
                                    </td>
                                    <td style="border:none;"><span class="action deleteReqrRow"
                                                                   onclick="deleteRowFromListInfoTable(this)">X</span>
                                    </td>
                                </tr>
                                <?php if(count($this->reqr) > 0) {
                                    foreach($this->reqr["type"] as $key => $type) { ?>
                                        <tr class="submit-reqrEntry">
                                            <td>
                                                <select class="submit-reqrType">
                                                    <option value="mail" <?= $type == 1 ? "selected" : "" ?>>Mail server
                                                        (please specify type type of mail server
                                                        required)
                                                    </option>
                                                    <option value="mysql" <?= $type == 2 ? "selected" : "" ?>>MySQL
                                                        database
                                                    </option>
                                                    <option value="apiToken" <?= $type == 3 ? "selected" : "" ?>>Service
                                                        API token (please specify what service)
                                                    </option>
                                                    <option value="password" <?= $type == 4 ? "selected" : "" ?>>
                                                        Passwords for services provided by the plugin
                                                    </option>
                                                    <option value="other" <?= $type == 5 ? "selected" : "" ?>>Other
                                                        (please specify)
                                                    </option>
                                                </select>
                                            </td>
                                            <td><input type="text" class="submit-reqrSpec"
                                                       value="<?= $this->reqr["details"][$key] ?>"/></td>
                                            <td>
                                                <select class="submit-reqrEnhc">
                                                    <option value="requirement" <?= $this->reqr["isRequire"][0] == 1 ? "selected" : "" ?>>
                                                        Requirement
                                                    </option>
                                                    <option value="enhancement" <?= $this->reqr["isRequire"][0] == 0 ? "selected" : "" ?>>
                                                        Enhancement
                                                    </option>
                                                </select>
                                            </td>
                                            <td style="border:none;"><span class="action deleteReqrRow"
                                                                           onclick="deleteRowFromListInfoTable(this)">X</span>
                                            </td>
                                        </tr>
                                    <?php }
                                } ?>
                            </table>
                        </div>
                        <span onclick='addRowToListInfoTable("baseReqrForm", "reqrValue");'
                              class="action">Add row</span>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-key">Icon</div>
                    <div class="form-value">
                        <?php if(is_object($icon)) { ?>
                            <p><img src="data:<?= $icon->mime ?>;base64,<?= base64_encode($icon->content) ?>"/></p>
                            <p class="explain">Release icon is imported from <?= htmlspecialchars($icon->name) ?> in
                                your repo. You can change the path by adding an
                                <code class="code">icon: path/to/icon.png</code> attribute in .poggit.yml under this
                                project's entry.</p>
                        <?php } else { ?>
                            <p><img src="<?= Poggit::getRootPath() ?>res/defaultPluginIcon2.png"/></p>
                            <p><span class="explain"><?= htmlspecialchars($icon) ?> You can change your icon by
                                adding an <code class="code">icon: path/to/icon.png</code> attribute in .poggit.yml
                                under this project's entry. The image you see now is the default plugin icon as a
                                substitution.</span>
                            </p>
                        <?php } ?>
                        <p><span class="explain">You will not be allowed to change your icon again unless you
                            submit a new version. Make sure you are submitting the right version!</span></p>
                    </div>
                </div>

                <div class="submitbuttons"><span class="action" id="submit-submitDraft">Save as Draft</span>
                    <span class="action" id="submit-submitReal">Submit plugin
                        <?= $this->module->lastRelease === [] ? "" : "update" ?>
                            </span>
                </div>
            </div>
            <div id="previewLicenseDetailsDialog">
                <h5><a id="previewLicenseName" target="_blank"></a></h5>
                <p id="previewLicenseDesc"></p>
                <pre id="previewLicenseBody"></pre>
            </div>
        </div>
        <?php
    }

    public function includeMoreJs() {
        $this->module->includeJs("submit");
    }
}
