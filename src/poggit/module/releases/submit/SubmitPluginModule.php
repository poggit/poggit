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

use poggit\module\Module;
use poggit\output\OutputManager;
use poggit\Poggit;
use poggit\session\SessionUtils;
use function poggit\redirect;

class SubmitPluginModule extends Module {
    private $owner;
    private $repo;
    private $project;
    private $buildClass;
    private $build;

    private $action;
    private $lastRelease;

    public function getName() : string {
        return "submit";
    }

    public function getAllNames() : array {
        return ["submit", "update"];
    }

    public function output() {
        $parts = array_filter(explode("/", $this->getQuery(), 5));
        if(count($parts) < 3 or isset($_REQUEST["showRules"])) {
            $this->showRulesPage();
            return;
        }
        if(count($parts) < 5) redirect("ci/$parts[0]/$parts[1]/$parts[2]#releases");
        list($this->owner, $this->repo, $this->project, $buildClass, $this->build) = $parts;
        $this->buildClass = array_search($buildClass, Poggit::$BUILD_CLASS_IDEN);
        if($this->buildClass === false or !is_numeric($this->build)) $this->errorBadRequest("Syntax: /submit/:owner/:repo/:project/:buildClass/:buildNumber");
        $this->build = (int) $this->build;

        if(!isset($_POST["readRules"]) or $_POST["readRules"] === "off") {
            $this->showRulesPage();
            return;
        }
        if(!SessionUtils::getInstance()->isLoggedIn()) {
            $this->requestLogin();
            return;
        }

        $lastRelease = Poggit::queryAndFetch("SELECT releases.* FROM releases
            INNER JOIN projects ON projects.projectId = releases.projectId
            INNER JOIN repos ON repos.repoId = projects.repoId
            WHERE repos.owner = ? AND repos.name = ? AND projects.name = ?
            ORDER BY creation DESC LIMIT 1", "sss", $this->owner, $this->repo, $this->project);
        if(count($lastRelease) === 1) {
            $this->action = "update";
            $this->lastRelease = $lastRelease[0];
        } else {
            $this->action = "submit";
        }

        $this->outputNormal();
    }

    private function showRulesPage() {
        $minifier = OutputManager::startMinifyHtml();
        ?>
        <html>
        <head>
            <title>Poggit Plugin Release Submission</title>
            <?php
            $this->headIncludes("Poggit Plugin Release Submission", "Enable plugin releases for builds from Poggit CI."
//                . " If your plugin is not on GitHub, this page will also help you import your plugin to GitHub."
            );
            ?>
        </head>
        <body>
        <?php $this->bodyHeader() ?>
        <div id="body">
            <h1>Poggit Release</h1>
            <div class="toggle" data-name="What is this?">
                <p>After you have created plugin builds using Poggit-CI, you can release them using Poggit Release.
                    Poggit Release is different from Poggit-CI &dash; Poggit-CI is for development builds, and Poggit
                    Release is for production (pre-)releases. It includes these features:</p>
                <ul>
                    <li>Official plugin reviews before publicly visible (users can still download from Poggit-CI, and
                        <a href="#">partly reviewed</a> releases can be seen by registered users)
                    </li>
                    <li>Reviews from users</li>
                    <!--                    <li>More detailed download statistics</li>-->
                    <li>No need to upload phars &dash; Just click the button and it will be copied from Poggit-CI!</li>
                    <li>Categorized with different meta information so that users can search easily</li>
                    <li>More detailed interface, e.g. licenses, update changelog, formatted descriptions</li>
                </ul>
            </div>
            <div class="toggle" data-name="Flow of plugin submission">
                <ol>
                    <li>Developer writes a plugin and pushes it to GitHub.</li>
                    <li>Developer enables Poggit-CI for the plugin.</li>
                    <li>On the project page on Poggit-CI, developer submits the plugin for release. Plugin is added to a
                        code review queue.
                    </li>
                    <li>Code reviewers will review the code of the plugin to confirm there is no malicious or
                        inappropriate code in the plugin. If approved, the plugin will be visible to registered members,
                        and moved to a test review queue.
                    </li>
                    <li>If plugin is approved by code reviewers, it is moved to a test review queue. Testers will test
                        the plugin against features listed in the plugin description.
                    </li>
                    <li>Plugin is released.</li>
                    <li>A developer can submit a new build in their Poggit-CI as release-ready on the Poggit-CI page,
                        and the build will go to the code review queue, and then start with 4. again.
                    </li>
                </ol>
            </div>
            <div class="toggle" data-name="Submission rules" data-opened="true">
                <ol style="list-style-type: upper-latin">
                    <li>Plugin quality:
                        <ol>
                            <li>Only plugins with clear (but not necessarily commented), unobfuscated (but not
                                necessarily strictly formatted) code will be reviewed. Obfuscated plugins or those with
                                unsavlgeably poor syntax will result in immediately rejection. Reviewers may raise
                                questions on suspicious code, which may delay the release of your plugin.
                            </li>
                            <li>The plugin must serve a purpose. Plugins that are too simple (for example, if there are
                                only several lines of code not related to declaring plugin or registering hooks) are not
                                allowed.
                            </li>
                            <li>The plugin must not execute arbitrary operations downloaded from the Internet, or by any
                                otherwise means execute anything that cannot be foreseen by code reviewers.
                                <ul>
                                    <li>For example, you cannot download an update of your plugin from your GitHub and
                                        replace the current plugin with it.
                                    </li>
                                    <li>You also cannot download a filename and file from an unauthorized source (e.g.
                                        your own website) and create it, because this may lead to vulnerabilities such
                                        as replacing the server ops.txt with your desired one.
                                    </li>
                                    <li>This only applies to code run in server runtime. Code in the phar stub, unless
                                        the plugin executes such code, is not subject to such limitation.
                                    </li>
                                    <li>However, Poggit provides a tool for automatic management of approved plugins, so
                                        you don't really need to write that yourself.
                                    </li>
                                </ul>
                            </li>
                            <li>The plugin should support all environments (OS, CPU architecture) supported by the
                                spoons you support, only unless the features are not feasible (or not closely feeasible)
                                in some environments.
                            </li>
                            <li>Creation (not loading), deletion (not disabling) or modification of other plugins or the
                                server core code is not allowed.
                            </li>
                            <li>The plugin should only use the default extensions as provided by its supported spoons.
                                Do not attach extensions to your plugin.
                            </li>
                            <li>Plugins that rely on main-thread Internet access are discouraged. If the use of
                                main-thread Internet access is significant enough to cause major effect on performance
                                or create easy DoS vulnerabilities (e.g. by spamming a command), the plugin may be
                                flagged as low-quality. Consider using <code class="code">AsyncTask</code> for online
                                operations, including MySQL queries. (While MySQL queries are usually fast, if used
                                excessively, they may still create significant lag)
                            </li>
                            <li>Plugins should avoid relying on downloading external resources. Place a default in the
                                plugin resources folder.
                            </li>
                        </ol>
                    </li>
                    <li>Plugin release:
                        <ol>
                            <li>Keep the plugin name simple. Do not include version-local information in your plugin
                                name, for example: <code class="code">SIRI MASSIVE UPDATE 4.0!!!!!</code>,
                                <code class="code">&gt;&gt;&gt;&gt;&gt;Craftmine 2.7.1, THE UPDATE THAT CHANGED THE
                                    SERVER, DOWNLOAD NOW!&lt;&lt;&lt;&lt;&lt;</code>
                            </li>
                            <li>Include all functionalities in the plugin description. Code reviewers may approach
                                authors if the description does not cover some features.
                            </li>
                            <li>Please enable GitHub Issues in the repo of the plugin so that reviewers can contact the
                                author. If Issues aren't available, commit comments may be used instead, which may be
                                less favourable to developers.
                            </li>
                            <li>The plugin submission page allows you to declare spoons compatible with this plugin.
                                Please, only declare spoons that you have indeed tested with.
                            </li>
                            <li>Specify all external libraries used by the plugin if necessary, as specified by the
                                license.
                            </li>
                            <li>Deletion and resubmission of a plugin to reset reviews will suspend your account from
                                submitting plugins. Poggit will clearly show the difference in review ratings throughout
                                different versions.
                            </li>
                            <li>While releasing a plugin (called Bar) that serves similar functionality to an existing
                                released plugin (called Foo) is allowed, Foo must not have exactly the same usage as or
                                less usage than Bar.
                            </li>
                            <li>If an existing plugin is absent in activity, i.e. the repo for the plugin has no new
                                code-related commits for at least 6 months, a derivative of the former plugin can be
                                submitted, only if it:
                                <ol style="list-style-type: lower-latin;">
                                    <li>updates the plugin from an old incompatible API with notable changes</li>
                                    <li>adds significant amount of new functionalities to the plugin</li>
                                    <li>clearly states that this plugin is a derivative of the former one</li>
                                </ol>
                                However, you are still encouraged to create pull requests to the original project as
                                long as possible, in order to minimize the number of plugins that serve the same
                                function.<br>
                                Otherwise, posting plugins by other people without proper authorization
                                will result in blocking your account from all of Poggit.
                            </li>
                            <li>Plugins must be released for free. There must not be locked features that require
                                payment. You are allowed to link for donations, but the donation must be voluntary, not
                                based on incentives from the plugin.
                            </li>
                            <li>Do not advertise Minecraft servers, YouTube channels, personal websites, etc. in
                                description or changelog. However, you may link to a YouTube video that shows how the
                                plugin works, but watching the video must only server as an aid &dash; the description /
                                changelog must be complete even without the video &dash; not everyone is good at
                                listening English/whatever language you use!
                            </li>
                            <li>Do not provide any external download links. Poggit will generate links to the Poggit-CI
                                development builds for you.
                            </li>
                        </ol>
                    </li>
                </ol>
            </div>

            <?php
            if(isset($this->build)) {
                ?>
                <form method='post' id='readRulesForm'>
                    <input type="hidden" name="readRules" value="on">
                    <p>I understand and agree with the above terms.
                        <span class="action" onclick='document.getElementById("readRulesForm").submit()'>
                            Submit the plugin now</span>
                    </p>
                </form>
                <?php
            }
            ?>
        </div>
        </body>
        </html>
        <?php
        OutputManager::endMinifyHtml($minifier);
    }

    private function requestLogin() {
        http_response_code(401);
        ?>
        <html>
        <head>
            <title>Please log in</title>
            <?php $this->headIncludes("Submit Release/Update | Poggit Releases") ?>
        </head>
        <body>
        <?php $this->bodyHeader() ?>
        <div id="body">
            <h1>Please log in.</h1>
            <p>Please <span class="action" onclick="login();">Login with GitHub</span> to submit a release.</p>
        </div>
        </body>
        </html>
        <?php
    }

    private function outputNormal() {
        $mainAction = isset($this->lastRelease) ? "Releasing update" : "Releasing plugin";
        $buildPath = Poggit::getRootPath() . "ci/$this->owner/$this->repo/$this->project/" . Poggit::$BUILD_CLASS_IDEN[$this->buildClass] . ":$this->build";
        ?>
        <html>
        <head>
            <title><?= $mainAction ?>: <?= $this->owner ?>/<?= $this->repo ?>/<?= $this->project ?></title>
            <?php $this->headIncludes($mainAction) ?>
        </head>
        <body>
        <?php $this->bodyHeader() ?>
        <div id="body">
            <h1><?= $mainAction ?>: <?= $this->owner ?>/<?= $this->repo ?>/<?= $this->project ?></h1>
            <p>Submitting build: <a href="<?= $buildPath ?>" target="_blank">
                    <?= Poggit::$BUILD_CLASS_HUMAN[$this->buildClass] ?> Build #<?= $this->build ?></a></p>
            <form method="post" action="<?= Poggit::getRootPath() ?>release.submit.callback">
                <!-- TODO receive callback -->
                <input type="hidden" name="owner" value="<?= htmlspecialchars($this->owner) ?>"/>
                <input type="hidden" name="repo" value="<?= htmlspecialchars($this->repo) ?>"/>
                <input type="hidden" name="project" value="<?= htmlspecialchars($this->project) ?>"/>
                <input type="hidden" name="buildClass" value="<?= htmlspecialchars($this->buildClass) ?>"/>
                <input type="hidden" name="build" value="<?= htmlspecialchars($this->build) ?>"/>
                <div class="form-table">
                    <div class="form-row">
                        <div class="form-key">Plugin name</div>
                        <div class="form-value">
                            <input type="text" size="32" name="name"
                                   value="<?= $this->lastRelease["name"] ?? $this->project ?>"/><br>
                            <span class="explain">Name of the plugin to be displayed. This can be different from the
                                project name, and must not repeat any existing names.</span></div>
                    </div>
                    <div class="form-row">
                        <div class="form-key">Tag line</div>
                        <div class="form-value">
                            <input type="text" size="64" maxlength="256" name="shortDesc"
                                   value="<?= $this->lastRelease["shortDesc"] ?? "" ?>"/><br>
                            <span class="explain">One-line text describing the plugin</span>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-key">Version name</div>
                        <div class="form-value">
                            <input type="text" name="version" size="10"/><br>
                            <span class="explain">Unique version name of this plugin release</span>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-key">Plugin Description</div>
                        <div class="form-value">
                            <textarea name="pluginDescription" id="pluginDescTextArea" cols="72"
                                      rows="10"></textarea><br>
                            Format: <select name="pluginDescType" id="pluginDescTypeSelect">
                                <option value="md">GitHub-Flavoured Markdown (context: github.com/<?= $this->owner ?>/<?= $this->repo ?></option>
                                <option value="txt">Plain text</option>
                            </select><br>
                            <span class="explain">Brief explanation of your plugin. You should include
                                <strong>all</strong> features provided by your plugin here so that reviewers won't be
                                confused by the code you write.</span>
                        </div>
                    </div>
                    <?php if(isset($this->lastRelease)) { ?>
                        <script>
                            //                                    $.get(<?//= json_encode(Poggit::getRootPath()) ?>// +"r/<?//= $this->lastRelease["description"] ?>//.md", "", function(data) {
                            //
                            //                                        document.getElementById("pluginDescTextArea").value = data;
                            //                                    }, "text");
                            $.ajax(<?= json_encode(Poggit::getRootPath()) ?> +"r/<?= $this->lastRelease["description"] ?>.md", {
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
                    <div class="form-row">
                        <div class="form-key">Is pre-release</div>
                        <div class="form-value">
                            <input type="checkbox" name="isPreRelease"><br>
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
                            <input type="file" name="pluginIcon"/><br>
                            <span class="explain">The icon for the plugin. Will use a REALLY VERY UGLY default icon if
                            none is provided.</span>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        </body>
        </html>
        <?php
    }
}
