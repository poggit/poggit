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
        $buildPath = Poggit::getRootPath() . "ci/{$this->module->owner}/{$this->module->repo}/{$this->module->project}/dev:{$this->module->build}";
        ?>
        <script>
            var pluginSubmitData = <?= json_encode([
                "owner" => $this->module->owner,
                "repo" => $this->module->repo,
                "project" => $this->module->project,
                "build" => $this->module->build
            ]) ?>;

            function addRowToListInfoTable(tableId, baseId) {
                var clone = $("#" + tableId).clone();
                clone.css("display", "table-row");
                clone.removeAttr("id");
                clone.appendTo($("#" + baseId));
                return clone;
            }
            function deleteRowFromListInfoTable(span) {
                $(span).parents("tr").remove();
            }
        </script>
        <div class="realsubmitwrapper">
            <div class="submittitle"><h1><?= $this->getTitle() ?></h1></div>
            <p>Submitting build: <a href="<?= $buildPath ?>" target="_blank">Build #<?= $this->module->build ?></a></p>
            <div class="form-table">
                <div class="form-row">
                    <div class="form-key">Plugin name</div>
                    <div class="form-value">
                        <input id="submit-pluginName" onblur="checkPluginName();" autofocus type="text" size="32"
                               value="<?= $this->module->lastRelease["name"] ?? $this->module->project ?>"
                            <?= isset($this->module->lastRelease["name"]) ? "disabled" : "" ?>
                        /><br/>
                        <span class="explain">Name of the plugin to be displayed. This can be different from the
                                project name, and it must not already exist.</span></div>
                </div>
                <div class="form-row">
                    <div class="form-key">Tag line</div>
                    <div class="form-value">
                        <input type="text" size="64" maxlength="256" id="submit-shortDesc"
                               value="<?= $this->module->lastRelease["shortDesc"] ?? "" ?>"/><br/>
                        <span class="explain">One-line text describing the plugin</span>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-key">Version name</div>
                    <div class="form-value">
                        <input type="text" id="submit-version" size="10"/><br/>
                        <span class="explain">Unique version name of this plugin release</span>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-key">Plugin Description</div>
                    <div class="form-value">
                        <textarea name="pluginDesc" id="submit-pluginDescTextArea" cols="72"
                                  rows="10"></textarea><br/>
                        Format: <select id="submit-pluginDescTypeSelect">
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
                                                button.text("Import description from " + <?= json_encode($this->module->repo) ?> +"/" + data[j].path);
                                                button.click((function(datum) {
                                                    return function() {
                                                        $.get(datum.download_url, {}, function(data) {
                                                            $("#submit-pluginDescTextArea").val(data);
                                                            $("#submit-pluginDescTypeSelect").val("md");
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
                                    document.getElementById("submit-pluginDescTextArea").value = data;
                                    console.log(xhr.responseURL); // TODO fix this for #submit-pluginDescTypeSelect
                                }
                            });
                        </script>
                    <?php } ?>
                </div>
                <?php if($this->module->lastRelease !== []) { ?>
                    <div class="form-row">
                        <div class="form-key">What's new</div>
                        <div class="form-value">
                            <textarea id="submit-pluginChangeLogTextArea" cols="72"
                                      rows="10"></textarea><br/>
                            Format: <select id="submit-pluginChangeLogTypeSelect">
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
                            <p>Also note that Poggit is not a legal firm. Please do not rely on Poggit for legal
                                license
                                information.</p>
                        </div>
                        <select id="submit-chooseLicense">
                            <option value="nil" selected>No license</option>
                            <option value="custom">Custom license</option>
                        </select>
                        <span class="action disabled" id="viewLicenseDetails">View license details</span><br/>
                        <textarea id="submit-customLicense" style="display: none;"
                                  placeholder="Custom license content" rows="30"></textarea>
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
                        })($("#submit-chooseLicense"), $("#viewLicenseDetails"), $("#submit-customLicense"));
                    </script>
                </div>
                <!-- TODO inherit from previous release -->
                <div class="form-row">
                    <div class="form-key">Pre-release</div>
                    <div class="form-value">
                        <input type="checkbox" id="submit-isPreRelease"><br/>
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
                        Major category: <select id="submit-majorCategory">
                            <?php
                            foreach(PluginRelease::$CATEGORIES as $id => $name) {
                                $selected = $id === 8 ? "selected" : "";
                                echo "<option value='$id' $selected>" . htmlspecialchars($name) . "</option>";
                            }
                            ?>
                        </select><br/>
                        Minor categories:
                        <div class="submitReleaseCats" class="submit-categories">
                            <?php
                            foreach(PluginRelease::$CATEGORIES as $id => $name) {
                                echo "<div class='cbinput'><input type='checkbox' value='$id'>" . htmlspecialchars($name) . "</input></div>";
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
                <div class="form-row">
                    <div class="form-key">Keywords</div>
                    <div class="form-value">
                        <input type="text" id="submit-keywords">
                        <p class="explain">Separate different keywords with spaces. These keywords will be used to
                            let
                            users search plugins. Synonyms are allowed, but use no more than 25 keywords.</p>
                    </div>
                </div>
                <!-- TODO inherit from previous release -->
                <div class="form-row">
                    <div class="form-key">Supported API versions</div>
                    <div class="form-value">
                        <span class="explain">The PocketMine <?php Poggit::ghLink("https://github.com/pmmp/PocketMine-MP") ?>
                            <em>API versions</em> supported by this plugin.<br/>
                            Please note that Poggit only accepts submission of plugins written and tested on PocketMine.
                            Plugins written for spoons are <strong>not</strong> accepted.
                        </span>
                        <table class="info-table" id="supportedSpoonsValue">
                            <tr>
                                <th><em>API</em> Version</th>
                            </tr>
                            <tr id="baseSpoonForm" class="submit-spoonEntry" style="display: none;">
                                <td><input type="text" class="submit-spoonVersion"/></td>
                                <td><span class="action deleteSpoonRow" onclick="deleteRowFromListInfoTable(this);">
                                    </span></td>
                            </tr>
                        </table>
                        <span onclick='addRowToListInfoTable("supportedSpoonsValue", "baseSpoonForm");'
                              class="action">Add row</span>
                    </div>
                    <script>addRowToListInfoTable("supportedSpoonsValue", "baseSpoonForm").find(".deleteSpoonRow").parent("td").remove();</script>
                </div>
                <!-- TODO inherit from previous release -->
                <div class="form-row">
                    <div class="form-key">Dependencies</div>
                    <div class="form-value">
                        <table class="info-table" id="dependenciesValue">
                            <tr>
                                <th>Plugin name</th>
                                <th>Compatible version</th>
                                <th>Relevant Poggit release</th>
                                <th>Required or optional?</th>
                            </tr>
                            <tr id="baseDepForm" class="submit-depEntry" style="display: none;">
                                <td><input type="text" class="submit-depName"/></td>
                                <td><input type="text" class="submit-depVersion"/></td>
                                <td>
                                    <input type="button" class="submit-depRelIdTrigger"/>
                                    <span class="submit-depRelId" data-relId="0"></span>
                                </td>
                                <td>
                                    <select class="submit-depSoftness">
                                        <option value="hard">Required</option>
                                        <option value="soft">Optional</option>
                                    </select>
                                </td>
                                <td><span class="action deleteDepRow" onclick="deleteRowFromListInfoTable(this)"></span>
                                </td>
                            </tr>
                        </table>
                        <span onclick='addRowToListInfoTable("dependenciesValue", "baseDepForm");'
                              class="action">Add row</span>
                        <span class="explain">Other plugins that this plugin requires to run with, or optionally works
                            with. You are recommended to put the latest version that the other plugin has been tested
                            to work with, but you don't need to update this value if new compatible versions of the
                            other plugin are released.
                        </span>
                    </div>
                    <script>addRowToListInfoTable("dependenciesValue", "baseDepForm").find(".deleteDepRow").parent("td").remove();</script>
                </div>
                <!-- TODO inherit from previous release -->
                <div class="form-row">
                    <div class="form-key">Permissions</div>
                    <div class="form-value">
                        <span class="explain">The actions on the server that this plugin does</span>
                        <div id="submit-perms">
                            <?php foreach(PluginRelease::$PERMISSIONS as $value => $message) { ?>
                                <div class="cbinput">
                                    <input type="checkbox" class="submit-permEntry" value="<?= $value ?>"/>
                                    <?= htmlspecialchars($message) ?>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>

                <!-- TODO requirements/enhancements -->

                <!--<div class="form-row">
                    <div class="form-key">Plugin Icon</div>
                    <div class="form-value">
                        <input type="file" name="pluginIcon" accept="image/*"/><br/>
                        <span class="explain">The icon for the plugin. Poggit will use a REALLY VERY UGLY default icon if
                            none is provided.</span>
                    </div>
                </div>-->
            </div>
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
        </div>
        <?php
    }
}
