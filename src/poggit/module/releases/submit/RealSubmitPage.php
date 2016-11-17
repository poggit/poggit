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
        return $this->mainAction . ":" . $this->module->owner . "/" . $this->module->repo . "/" . $this->module->project;
    }

    public function output() {
        $buildPath = Poggit::getRootPath() . "ci/{$this->module->owner}/{$this->module->repo}/{$this->module->project}/" .
            Poggit::$BUILD_CLASS_IDEN[$this->module->buildClass] . ":{$this->module->build}";
        ?>
        <h1><?= $this->getTitle() ?></h1>
        <p>Submitting build: <a href="<?= $buildPath ?>" target="_blank">
                <?= Poggit::$BUILD_CLASS_HUMAN[$this->module->buildClass] ?> Build #<?= $this->module->build ?></a></p>
        <form method="post" action="<?= Poggit::getRootPath() ?>release.submit.callback">
            <!-- TODO receive callback -->
            <input type="hidden" name="owner" value="<?= htmlspecialchars($this->module->owner) ?>"/>
            <input type="hidden" name="repo" value="<?= htmlspecialchars($this->module->repo) ?>"/>
            <input type="hidden" name="project" value="<?= htmlspecialchars($this->module->project) ?>"/>
<!--            <input type="hidden" name="buildClass" value="--><?//= htmlspecialchars($this->module->buildClass) ?><!--"/>-->
            <input type="hidden" name="build" value="<?= htmlspecialchars($this->module->build) ?>"/>
            <input type="hidden" name="antiForge" value="<?= SessionUtils::getInstance()->getAntiForge() ?>"/>
            <div class="form-table">
                <div class="form-row">
                    <div class="form-key">Plugin name</div>
                    <div class="form-value">
                        <input type="text" size="32" name="name"
                               value="<?= $this->module->lastRelease["name"] ?? $this->module->project ?>"/><br/>
                        <span class="explain">Name of the plugin to be displayed. This can be different from the
                                project name, and must not repeat any existing names.</span></div>
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
                            <textarea name="pluginDescription" id="pluginDescTextArea" cols="72"
                                      rows="10"></textarea><br/>
                        Format: <select name="pluginDescType" id="pluginDescTypeSelect">
                            <option value="md">GitHub-Flavoured Markdown (context:
                                github.com/<?= $this->module->owner ?>/<?= $this->module->repo ?></option>
                            <option value="txt">Plain text</option>
                        </select><br/>
                        <span class="explain">Brief explanation of your plugin. You should include
                                <strong>all</strong> features provided by your plugin here so that reviewers won't be
                                confused by the code you write.</span>
                    </div>
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
                <?php if($this->module->lastRelease !== []) { ?>
                    <div class="form-row">
                        <div class="form-key">What's new</div>
                        <div class="form-value">
                            <textarea name="pluginChangeLog" id="pluginChangeLogTextArea" cols="72"
                                      rows="10"></textarea><br/>
                            Format: <select name="pluginChangeLogType" id="pluginChangeLogTypeSelect">
                                <option value="md">GitHub-Flavoured Markdown (context:
                                    github.com/<?= $this->module->owner ?>/<?= $this->module->repo ?></option>
                                <option value="txt">Plain text</option>
                            </select><br/>
                            <span class="explain">Changelog for this update. Briefly point out what this update has
                                brought. This information is used by plugin reviewers.</span>
                        </div>
                    </div>
                <?php } ?>
                <div class="form-row">
                    <div class="form-key">Is pre-release</div>
                    <div class="form-value">
                        <input type="checkbox" name="isPreRelease"><br/>
                        <span class="explain">A pre-release is a preview of a release of your plugin. It must still
                                be functional, although some features may not be completed yet (you must emphasize this
                                in the description!), and may be a bit buggy or unstable (but if it is too terrible, it
                                will still not get approved).
                            </span>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-key">Plugin Icon</div>
                    <div class="form-value">
                        <input type="file" name="pluginIcon"/><br/>
                        <span class="explain">The icon for the plugin. Will use a REALLY VERY UGLY default icon if
                            none is provided.</span>
                    </div>
                </div>
            </div>
        </form>
        <?php
    }
}
