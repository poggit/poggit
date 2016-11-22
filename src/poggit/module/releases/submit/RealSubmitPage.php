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

namespace poggit\module\releases\submit;

use poggit\model\PluginRelease;
use poggit\module\VarPage;
use poggit\Poggit;
use poggit\session\SessionUtils;

class RealSubmitPage extends VarPage {
    /** @var SubmitPluginModule */
    private $module;
    private $mainAction;

    public function __construct(SubmitPluginModule $module) {
        $this->module = $module;
        $this->mainAction = ($this->module->lastRelease !== []) ? "Releasing update" : "Releasing plugin";
    }

    public function getTitle(): string {
        return $this->mainAction . ": " . $this->module->owner . "/" . $this->module->repo . "/" . $this->module->project;
    }

    public function output() {
        $buildPath = Poggit::getRootPath() . "ci/{$this->module->owner}/{$this->module->repo}/{$this->module->project}/" .
            Poggit::$BUILD_CLASS_IDEN[$this->module->buildClass] . ":{$this->module->build}";
        ?>
        <div class="submittitle"><h1><?= $this->getTitle() ?></h1></div>
        <p>Submitting build: <a href="<?= $buildPath ?>" target="_blank">
                <?= Poggit::$BUILD_CLASS_HUMAN[$this->module->buildClass] ?> Build #<?= $this->module->build ?></a></p>
        <form id="submitReleaseForm" method="post" action="<?= Poggit::getRootPath() ?>release.submit.callback">
            <!-- TODO receive callback -->
            <input type="hidden" name="owner" value="<?= htmlspecialchars($this->module->owner) ?>"/>
            <input type="hidden" name="repo" value="<?= htmlspecialchars($this->module->repo) ?>"/>
            <input type="hidden" name="project" value="<?= htmlspecialchars($this->module->project) ?>"/>
            <input type="hidden" name="build" value="<?= htmlspecialchars($this->module->build) ?>"/>
            <input type="hidden" name="antiForge" value="<?= SessionUtils::getInstance()->getAntiForge() ?>"/>
            <div class="form-table">
                <div class="form-row">
                    <div class="form-key">Plugin name</div>
                    <div class="form-value">
                        <input type="text" size="32" name="name"
                               value="<?= $this->module->lastRelease["name"] ?? $this->module->project ?>"/><br/>
                        <span class="explain">Name of the plugin to be displayed. This can be different from the
                                project name, and it must not already exist.</span></div>
                </div>
                <div class="form-row">
                    <div class="form-key">Tag line</div>
                    <div class="form-value">
                        <input type="text" size="64" maxlength="256" name="shortDesc"
                               value="<?= $this->module->lastRelease["shortDesc"] ?? "" ?>"/><br/>
                        <span class="explain">One-line text describing the plugin</span>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-key">Version name</div>
                    <div class="form-value">
                        <input type="text" name="version" size="10"/><br/>
                        <span class="explain">Unique version name of this plugin release</span>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-key">Plugin Description</div>
                    <div class="form-value">
                        <textarea name="pluginDesc" id="pluginDescTextArea" cols="72"
                                  rows="10"></textarea><br/>
                        Format: <select name="pluginDescType" id="pluginDescTypeSelect">
                            <option value="md">GH Markdown (context:
                                github.com/<?= $this->module->owner ?>/<?= $this->module->repo ?>)
                            </option>
                            <option value="txt">Plain text</option>
                        </select><br/>
                        <div id="possibleDescriptionImports"></div>
                        <script>
                            <?php
                            $possible = [""];
                            $projectPath = $this->module->projectDetails["path"];
                            if($projectPath !== "") {
                                $possible[] = "/" . $projectPath;
                            }
                            ?>
                            (function(possibleDirs) {
                                for(var i = 0; i < possibleDirs.length; i++) {
                                    var url = "repositories/<?=(int) $this->module->projectDetails["repoId"]?>/contents" + possibleDirs[i];
                                    ghApi(url, {}, "GET", function(data) {
                                        for(var j = 0; j < data.length; j++) {
                                            if(data[j].type == "file" && (data[j].name == "README" || data[j].name == "README.md" || data[j].name == "README.txt")) {
                                                var button = $("<span class='action'></span>");
                                                button.text("Import description from " + <?= json_encode($this->module->repo) ?> + "/" + data[j].path);
                                                button.click((function(datum) {
                                                    return function() {
                                                        $.get(datum.download_url, {}, function(data) {
                                                            $("#pluginDescTextArea").val(data);
                                                            $("#pluginDescTypeSelect").val("md");
                                                        })
                                                    };
                                                })(data[j]));
                                                button.appendTo($("#possibleDescriptionImports"));
                                            }
                                        }
                                    });
                                }
                            })(<?=json_encode($possible)?>);
                        </script>
                        <br/>
                        <span class="explain">Brief explanation of your plugin. You should include
                                <strong>all</strong> features provided by your plugin here so that reviewers won't be
                                confused by the code you write.</span>
                    </div>
                    <?php if($this->module->lastRelease !== []) { ?>
                        <script>
                            $.ajax(<?= json_encode(Poggit::getRootPath()) ?> +"r/<?= $this->module->lastRelease["description"] ?>.md", {
                                dataType: "text",
                                headers: {
                                    Accept: "text/plain"
                                },
                                success: function(data, status, xhr) {
                                    document.getElementById("pluginDescTextArea").value = data;
                                    console.log(xhr.responseURL); // TODO fix this for #pluginDescTypeSelect
                                }
                            });
                        </script>
                    <?php } ?>
                </div>
                <?php if($this->module->lastRelease !== []) { ?>
                    <div class="form-row">
                        <div class="form-key">What's new</div>
                        <div class="form-value">
                            <textarea name="pluginChangeLog" id="pluginChangeLogTextArea" cols="72"
                                      rows="10"></textarea><br/>
                            Format: <select name="pluginChangeLogType" id="pluginChangeLogTypeSelect">
                                <option value="md">GH Markdown (context:
                                    github.com/<?= $this->module->owner ?>/<?= $this->module->repo ?>)
                                </option>
                                <option value="txt">Plain text</option>
                            </select><br/>
                            <span class="explain">Changelog for this update. Briefly point out what is new in this update.
                            This information is used by plugin reviewers.</span>
                        </div>
                    </div>
                <?php } ?>
                <!-- TODO inherit from previous release -->
                <div class="form-row">
                    <div class="form-key">License</div>
                    <div class="form-value">
                        <div class="explain">
                            <p>Choose a license to be displayed in the plugin page. The templates
                                provided by GitHub will be used. Poggit will not try to fetch the license from your
                                GitHub
                                repo. You may also put a custom license here.</p>
                            <p>Also note that Poggit is not a legal firm. Please do not rely on Poggit for legal license
                                information.</p>
                        </div>
                        <select name="licenseType" id="chooseLicense">
                            <option value="nil" selected>No license</option>
                            <option value="custom">Custom license</option>
                        </select>
                        <span class="action disabled" id="viewLicenseDetails">View license details</span><br/>
                        <textarea name="licenseCustom" id="customLicense" style="display: none;"></textarea>
                    </div>
                    <script>
                        (function(licenseSelect, viewLicense, customLicense) {
                            viewLicense.click(function() {
                                var $this = $(this);
                                if($this.hasClass("disabled")) {
                                    return;
                                }
                                var dialog = $("#previewLicenseDetailsDialog");
                                var aname = dialog.find("#previewLicenseName");
                                var pdesc = dialog.find("#previewLicenseDesc");
                                var preBody = dialog.find("#previewLicenseBody");
                                dialog.dialog("open");
                                ghApi("licenses/" + licenseSelect.val(), {}, "GET", function(data) {
                                    aname.attr("href", data.html_url);
                                    aname.text(data.name);
                                    pdesc.text(data.description);
                                    preBody.text(data.body);
                                }, undefined, "Accept: application/vnd.github.drax-preview+json");
                            });
                            licenseSelect.change(function() {
                                customLicense.css("display", this.value == "custom" ? "block" : "none");
                                var url = $(this).find(":selected").attr("data-url");
                                if(typeof url == "string" && url.length > 0) {
                                    viewLicense.removeClass("disabled");
                                } else {
                                    viewLicense.addClass("disabled");
                                }
                            });
                            ghApi("licenses", {}, "GET", function(data) {
                                data.sort(function(a, b) {
                                    if(a.featured && !b.featured) {
                                        return -1;
                                    }
                                    if(!a.featured && b.featured) {
                                        return 1;
                                    }
                                    return a.key.localeCompare(b.key);
                                });
                                for(var i = 0; i < data.length; i++) {
                                    var option = $("<option></option>");
                                    option.attr("value", data[i].key);
                                    option.attr("data-url", data[i].url);
                                    option.text(data[i].name);
                                    option.appendTo(licenseSelect);
                                }
                            }, undefined, "Accept: application/vnd.github.drax-preview+json");
                        })($("#chooseLicense"), $("#viewLicenseDetails"), $("#customLicense"));
                    </script>
                </div>
                <!-- TODO inherit from previous release -->
                <div class="form-row">
                    <div class="form-key">Pre-release</div>
                    <div class="form-value">
                        <input type="checkbox" name="isPreRelease"><br/>
                        <span class="explain">A pre-release is a preview of a release of your plugin. It must still
                                be functional even if some features are not completed, and you must emphasize this
                                in the description. Pre-releases can be a bit buggy or unstable, but not too much or they
                                will not be approved.
                        </span>
                    </div>
                </div>
                <!-- TODO inherit from previous release, and disable if inherited? -->
                <div class="form-row">
                    <div class="form-key">Categories</div>
                    <div class="form-value">
                        Major category: <select name="majorCategory">
                            <?php
                            foreach(PluginRelease::$CATEGORIES as $id => $name) {
                                $selected = $id === 8 ? "selected" : "";
                                echo "<option value='$id' $selected>" . htmlspecialchars($name) . "</option>";
                            }
                            ?>
                        </select><br/>
                        Minor categories:
                        <div class="submitreleasecategories">
                            <?php
                            foreach(PluginRelease::$CATEGORIES as $id => $name) {
                                echo "<div class='cbinput'><input name='minorCategories[]' type='checkbox' value='$id'>" . htmlspecialchars($name) . "</input></div>";
                            }
                            ?>
                        </div>
                        <p class="explain">This plugin will be listed in the major category, but users subscribing to
                            the minor categories will also be notified when this plugin is released.<br/>
                            You do not need to select the major category in minor categories</p>
                    </div>
                </div>
                <!-- TODO inherit from previous release -->
                <div class="form-row">
                    <div class="form-key">Keywords</div>
                    <div class="form-value">
                        <input type="text" name="keywords">
                        <p class="explain">Separate different keywords with spaces. These keywords will be used to let
                            users search plugins. Synonyms are allowed, but use no more than 25 keywords.</p>
                    </div>
                </div>
                <!-- TODO supported spoons, dependencies, permissions, requirements -->
                <div class="form-row">
                    <div class="form-key">Plugin Icon</div>
                    <div class="form-value">
                        <input type="file" name="pluginIcon"/><br/>
                        <span class="explain">The icon for the plugin. Poggit will use a REALLY VERY UGLY default icon if
                            none is provided.</span>
                    </div>
                </div>
            </div>
        </form>
        <div id="previewLicenseDetailsDialog">
            <h5><a id="previewLicenseName" target="_blank"></a></h5>
            <p id="previewLicenseDesc"></p>
            <pre id="previewLicenseBody"></pre>
        </div>
        <script>
            $("#previewLicenseDetailsDialog").dialog({
                autoOpen: false,
                width: 600,
                clickOut: true,
                responsive: true,
                height: window.innerHeight * 0.8,
                position: {my: "center top", at: "center top+50", of: window}
            });
        </script>
        <?php
    }
}
