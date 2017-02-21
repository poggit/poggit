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

namespace poggit\module\releases\project;

use poggit\builder\ProjectBuilder;
use poggit\module\Module;
use poggit\Poggit;
use poggit\utils\internet\MysqlUtils;
use poggit\release\PluginRelease;
use poggit\utils\PocketMineApi;
use poggit\resource\ResourceManager;
use poggit\utils\SessionUtils;
use poggit\embed\EmbedUtils;
use poggit\module\releases\review\OfficialReviewModule as Review;

class ProjectReleasesModule extends Module {
    private $doStateReplace = false;
    private $release;

    private $projectName;
    private $name;
    private $version;
    private $description;
    private $license;
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
    private $icon;
    private $state;

    private $buildInternal;
    private $buildCount;
    private $thisBuildCommit;
    private $lastBuildCommit;
    private $topReleaseCommit;
    private $buildCompareURL;
    private $releaseCompareURL;
    private $lastBuildInternal;
    private $lastReleaseCreated;
    private $lastReleaseInternal;
    private $lastReleaseClass;
    private $lastBuildClass;

    public function getName(): string {
        return "release";
    }

    public function getAllNames(): array {
        return ["release", "rel", "plugin", "p"];
    }

    public function output() {
        $parts = array_filter(explode("/", $this->getQuery(), 2));
        $preReleaseCond = (!isset($_REQUEST["pre"]) or (isset($_REQUEST["pre"]) and $_REQUEST["pre"] != "off")) ? "(1 = 1)" : "((r.flags & 2) = 2)";
        $stmt = /** @lang MySQL */
            "SELECT r.releaseId, r.name, UNIX_TIMESTAMP(r.creation) AS created, b.sha, b.cause AS cause,  UNIX_TIMESTAMP(b.created) AS buildcreated,
                r.shortDesc, r.version, r.artifact, r.buildId, r.licenseRes, artifact.type AS artifactType, artifact.dlCount AS dlCount, 
                r.description, descr.type AS descrType, r.icon,
                r.changelog, changelog.type AS changeLogType, r.license, r.flags, r.state, b.internal AS internal, b.class AS class,
                rp.owner AS author, rp.name AS repo, p.name AS projectName, p.projectId, p.path, p.lang AS hasTranslation,
                (SELECT COUNT(*) FROM releases r3 WHERE r3.projectId = r.projectId)
                FROM releases r
                INNER JOIN projects p ON r.projectId = p.projectId
                INNER JOIN repos rp ON p.repoId = rp.repoId
                INNER JOIN resources artifact ON r.artifact = artifact.resourceId
                INNER JOIN resources descr ON r.description = descr.resourceId
                INNER JOIN resources changelog ON r.changelog = changelog.resourceId
                INNER JOIN builds b ON r.buildId = b.buildId
                WHERE r.name = ? AND $preReleaseCond ORDER BY r.state DESC";
        if(count($parts) === 0) Poggit::redirect("pi");
        if(count($parts) === 1) {
            $author = null;
            $name = $parts[0];
            $projects = MysqlUtils::query($stmt, "s", $name);
            if(count($projects) === 0) Poggit::redirect("pi?term=" . urlencode($name) . "&error=" . urlencode("No plugins called $name"));
            //if(count($projects) > 1) Poggit::redirect("plugins/called/" . urlencode($name));
            $release = $projects[0];
            if(count($projects) > 1) {
                $this->topReleaseCommit = json_decode($projects[1]["cause"])->commit;
                $this->lastReleaseInternal = (int) $projects[1]["internal"];
                $this->lastReleaseClass = ProjectBuilder::$BUILD_CLASS_HUMAN[$projects[1]["class"]];
                $this->lastReleaseCreated = (int) $projects[1]["buildcreated"];
            }
        } else {
            assert(count($parts) === 2);
            list($name, $requestedVersion) = $parts;
            $projects = MysqlUtils::query($stmt, "s", $name);

            // TODO refactor this to include the author code below

//          if(count($projects) === 0) Poggit::redirect("pi?author=" . urlencode($author) . "&term=" . urlencode($name));
            if(count($projects) > 1) {
//                foreach($projects as $project) {
//                    if(strtolower($project["author"]) === strtolower($author)) {
//                        $release = $project;
//                        break;
//                    }
//                }
//                if(!isset($release)) Poggit::redirect("pi?author=" . urlencode($author) . "&term=" . urlencode($name));
//                $this->doStateReplace = true;
//            } else {
                $i = 0;
                foreach($projects as $project) {
                    $i++;
                    if($project["version"] == $requestedVersion) {
                        $release = $project;
                        if($i > 1) {
                            $this->topReleaseCommit = json_decode($projects[0]["cause"])->commit;
                            $this->lastReleaseInternal = $projects[0]["internal"];
                            $this->lastReleaseClass = ProjectBuilder::$BUILD_CLASS_HUMAN[$projects[0]["class"]];
                            $this->lastReleaseCreated = (int) $projects[0]["buildcreated"];
                            break;
                        } else {
                            $this->topReleaseCommit = json_decode($projects[1]["cause"])->commit;
                            $this->lastReleaseInternal = $projects[1]["internal"];
                            $this->lastReleaseClass = ProjectBuilder::$BUILD_CLASS_HUMAN[$projects[1]["class"]];
                            $this->lastReleaseCreated = (int) $projects[1]["buildcreated"];
                            break;
                        }
                    }
                }
                $this->doStateReplace = true;
                if(!isset($release)) Poggit::redirect((count($projects) > 0 ? "p/" : "pi?term=") . urlencode($name));
            } else {
                if(count($projects) > 0) { // exactly 1 result
                    $release = $projects[0];
                } else {
                    Poggit::redirect("pi?term=" . urlencode($name));
                }
            }
        }
        /** @var array $release */
        $this->release = $release;

        $allBuilds = MysqlUtils::query("SELECT buildId, cause, internal, class FROM builds b WHERE b.projectId = ? ORDER BY buildId DESC", "i", $this->release["projectId"]);
        $this->buildCount = count($allBuilds);
        $getnext = false;
        foreach($allBuilds as $buildRow) {
            $cause = json_decode($buildRow["cause"]);
            if($getnext) {
                if($this->lastReleaseClass . $this->lastReleaseInternal != $buildRow["class"] . $buildRow["internal"]) {
                    $this->lastBuildCommit = $cause->commit ?? 0;
                    $this->lastBuildInternal = (int) $buildRow["internal"];
                    $this->lastBuildClass = ProjectBuilder::$BUILD_CLASS_HUMAN[$buildRow["class"]];
                }
                break;
            }
            if($buildRow["buildId"] == $this->release["buildId"]) {
                $this->thisBuildCommit = $cause->commit ?? 0;
                $getnext = true;
            }
        }
        $this->buildCompareURL = ($this->lastBuildCommit && $this->thisBuildCommit) ? "http://github.com/" . urlencode($this->release["author"]) . "/" .
            urlencode($this->release["repo"]) . "/compare/" . $this->lastBuildCommit . "..." . $this->thisBuildCommit : "";
        $this->releaseCompareURL = ($this->topReleaseCommit && $this->thisBuildCommit && ($this->lastReleaseCreated < $this->release["buildcreated"])) ? "http://github.com/" . urlencode($this->release["author"]) . "/" .
            urlencode($this->release["repo"]) . "/compare/" . $this->topReleaseCommit . "..." . $this->thisBuildCommit : "";

        $this->release["description"] = (int) $this->release["description"];
        $descType = MysqlUtils::query("SELECT type FROM resources WHERE resourceId = ? LIMIT 1", "i", $this->release["description"]);
        $this->release["desctype"] = $descType[0]["type"];
        $this->release["releaseId"] = (int) $this->release["releaseId"];
        $this->release["buildId"] = (int) $this->release["buildId"];
        $this->release["internal"] = (int) $this->release["internal"];
        // Changelog
        $this->release["changelog"] = (int) $this->release["changelog"];
        if($this->release["changelog"] !== ResourceManager::NULL_RESOURCE) {
            $clTypeRow = MysqlUtils::query("SELECT type FROM resources WHERE resourceId = ? LIMIT 1", "i", $this->release["changelog"]);
            $this->release["changelogType"] = $clTypeRow[0]["type"];
        } else {
            $this->release["changelog"] = null;
            $this->release["changelogType"] = null;
        }
        // Keywords
        $keywordRow = MysqlUtils::query("SELECT word FROM release_keywords WHERE projectId = ?", "i", $this->release["projectId"]);
        $this->release["keywords"] = [];
        foreach($keywordRow as $row) {
            $this->release["keywords"][] = $row["word"];
        }
        // Categories
        $categoryRow = MysqlUtils::query("SELECT category, isMainCategory FROM release_categories WHERE projectId = ?", "i", $this->release["projectId"]);
        $this->release["categories"] = [];
        $this->release["maincategory"] = 1;
        foreach($categoryRow as $row) {
            if($row["isMainCategory"] == 1) {
                $this->release["maincategory"] = (int) $row["category"];
            } else {
                $this->release["categories"][] = (int) $row["category"];
            }
        }
        // Spoons
        $this->release["spoons"] = [];
        $spoons = MysqlUtils::query("SELECT since, till FROM release_spoons WHERE releaseId = ?", "i", $this->release["releaseId"]);
        if(count($spoons) > 0) {
            foreach($spoons as $row) {
                $this->release["spoons"]["since"][] = $row["since"];
                $this->release["spoons"]["till"][] = $row["till"];
            }
        }
        //Permissions
        $this->release["permissions"] = [];
        $perms = MysqlUtils::query("SELECT val FROM release_perms WHERE releaseId = ?", "i", $this->release["releaseId"]);
        if(count($perms) > 0) {
            foreach($perms as $row) {
                $this->release["permissions"][] = $row["val"];
            }
        }
        // Dependencies
        $this->release["deps"] = [];
        $deps = MysqlUtils::query("SELECT name, version, depRelId, isHard FROM release_deps WHERE releaseId = ?", "i", $this->release["releaseId"]);
        if(count($deps) > 0) {
            foreach($deps as $row) {
                $this->release["deps"]["name"][] = $row["name"];
                $this->release["deps"]["version"][] = $row["version"];
                $this->release["deps"]["depRelId"][] = (int) $row["depRelId"];
                $this->release["deps"]["isHard"][] = (int) $row["isHard"];
            }
        }
        // Requirements
        $this->release["reqr"] = [];
        $reqr = MysqlUtils::query("SELECT type, details, isRequire FROM release_reqr WHERE releaseId = ?", "i", $this->release["releaseId"]);
        if(count($reqr) > 0) {
            foreach($reqr as $row) {
                $this->release["reqr"]["type"][] = $row["type"];
                $this->release["reqr"]["details"][] = $row["details"];
                $this->release["reqr"]["isRequire"][] = (int) $row["isRequire"];
            }
        }

        $this->state = (int) $this->release["state"];
        $session = SessionUtils::getInstance();
        $user = $session->getLogin()["name"] ?? "";
        $isStaff = Poggit::getAdmlv($user) >= Poggit::MODERATOR;
        $isMine = $user == $this->release["author"];
        if((($this->state < PluginRelease::MIN_PUBLIC_RELSTAGE && !$session->isLoggedIn()) || $this->state < PluginRelease::RELEASE_STAGE_CHECKED && $session->isLoggedIn()) && (!$isMine && !$isStaff)) {
            Poggit::redirect("p?term=" . urlencode($name) . "&error=" . urlencode("You are not allowed to view this resource"));
        }
        $this->projectName = $this->release["projectName"];
        $this->name = $this->release["name"];
        $this->buildInternal = $this->release["internal"];
        $this->description = ($this->release["description"]) ? file_get_contents(ResourceManager::getInstance()->getResource($this->release["description"])) : "No Description";
        $this->version = $this->release["version"];
        $this->shortDesc = $this->release["shortDesc"];
        $this->licenseDisplayStyle = ($this->release["license"] == "custom") ? "display: true" : "display: none";
        $this->licenseText = ($this->release["licenseRes"]) ? file_get_contents(ResourceManager::getInstance()->getResource($this->release["licenseRes"])) : "";
        $this->license = $this->release["license"];
        $this->changelogText = ($this->release["changelog"]) ? file_get_contents(ResourceManager::getInstance()->getResource($this->release["changelog"])) : "";
        $this->changelogType = ($this->release["changelogType"]) ? $this->release["changelogType"] : "md";
        $this->keywords = ($this->release["keywords"]) ? implode(" ", $this->release["keywords"]) : "";
        $this->categories = ($this->release["categories"]) ? $this->release["categories"] : [];
        $this->spoons = ($this->release["spoons"]) ? $this->release["spoons"] : [];
        $this->permissions = ($this->release["permissions"]) ? $this->release["permissions"] : [];
        $this->deps = ($this->release["deps"]) ? $this->release["deps"] : [];
        $this->reqr = ($this->release["reqr"]) ? $this->release["reqr"] : [];
        $this->mainCategory = ($this->release["maincategory"]) ? $this->release["maincategory"] : 1;
        ($this->release["desctype"]) ? $this->descType : "md";
        $this->icon = $this->release["icon"];
        $this->artifact = (int) $this->release["artifact"];

        $earliestDate = (int) MysqlUtils::query("SELECT MIN(UNIX_TIMESTAMP(creation)) AS created FROM releases WHERE projectId = ?",
            "i", (int) $release["projectId"])[0]["created"];
//        $tags = Poggit::queryAndFetch("SELECT val FROM release_meta WHERE releaseId = ? AND type = ?", "ii", (int) $release["releaseId"], (int)ReleaseConstants::TYPE_CATEGORY);
        ?>
        <html>
        <head prefix="og: http://ogp.me/ns# fb: http://ogp.me/ns/fb# object: http://ogp.me/ns/object# article: http://ogp.me/ns/article# profile: http://ogp.me/ns/profile#">
            <title><?= htmlspecialchars($release["name"]) ?></title>
            <meta property="article:published_time" content="<?= date(DATE_ISO8601, $earliestDate) ?>"/>
            <meta property="article:modified_time" content="<?= date(DATE_ISO8601, (int) $release["created"]) ?>"/>
            <meta property="article:author" content="<?= $release["name"] ?>"/>
            <meta property="article:section" content="Plugins"/>
            <?php $this->headIncludes($release["name"] . " - Download from Poggit", $release["shortDesc"], "article", "") ?>
            <meta name="twitter:image:src" content="<?= $this->icon ?? "" ?>">
        </head>
        <?php $this->bodyHeader() ?>
        <div id="body">
            <div class="release-top">
                <?php
                $link = Poggit::getRootPath() . "r/" . $this->artifact . "/" . $this->projectName . ".phar";
                $editlink = Poggit::getRootPath() . "update/" . $this->release["author"] . "/" . $this->release["repo"] . "/" . $this->projectName . "/" . $this->buildInternal;
                ?>
                <div class="downloadrelease">
                            <span class="action"
                                  onclick='window.location = <?= json_encode($link, JSON_UNESCAPED_SLASHES) ?>;'>
                                    Direct Download</span>
                </div>
                <?php
                $user = SessionUtils::getInstance()->getLogin()["name"] ?? "";
                if($user == $this->release["author"] || Poggit::getAdmlv($user) >= Poggit::MODERATOR) { ?>
                    <div class="editrelease">
                        <span class="action" onclick="location.href='<?= $editlink ?>'">Edit Release</span>
                    </div>
                <?php } ?>
                <?php if(Poggit::getAdmlv($user) >= Poggit::MODERATOR) { ?>
                    <div class="editRelease">
                        <select id="setStatus" class="inlineselect">
                            <?php foreach(PluginRelease::$STAGE_HUMAN as $key => $name) { ?>
                                <option value="<?= $key ?>" <?= $this->state == $key ? "selected" : "" ?>><?= $name ?></option>
                            <?php } ?>
                        </select>
                        <span class="update-status" onclick="updateRelease()">Set Status</span>
                    </div>
                <?php } ?>
            </div>
            <div class="plugin-heading">
                <div class="plugin-title">
                    <h1>
                        <a href="<?= Poggit::getRootPath() ?>ci/<?= $this->release["author"] ?>/<?= $this->release["repo"] ?>/<?= urlencode(
                            $this->projectName) ?>">
                            <?= htmlspecialchars($this->projectName) ?>
                            <?php
                            $tree = $this->release["sha"] ? ("tree/" . $this->release["sha"]) : "";
                            EmbedUtils::ghLink("https://github.com/{$this->release["author"]}/{$this->release["repo"]}/$tree");
                            ?>
                        </a>
                    </h1>
                    <h3>By <a href="<?= Poggit::getRootPath() . "ci/" . $this->release["author"] ?>"><?= $this->release["author"] ?></a></h3>
                </div>
                <div class="plugin-header-info">
                    <span id="releaseState"
                          class="plugin-state-<?= $this->state ?>"><?php echo htmlspecialchars(PluginRelease::$STAGE_HUMAN[$this->state]) ?></span>
                    <?php if($this->version !== "") { ?>
                        <div class="plugin-info">
                            Version<h3><?= $this->version ?></h3>
                        </div>
                    <?php } ?>
                    <?php if($this->shortDesc !== "") { ?>
                        <div class="plugin-info">
                            <p>Summary
                            <h3><?= $this->shortDesc ?></h3></p>
                        </div>
                    <?php } ?></div>
                <div class="plugin-logo">
                    <?php if($this->icon === null) { ?>
                        <img src="<?= Poggit::getRootPath() ?>res/defaultPluginIcon2.png" height="128"/>
                    <?php } else { ?>
                        <img src="<?= $this->icon ?>" height="128"/>
                    <?php } ?>
                </div>
            </div>
            <?php if ($this->state <= PluginRelease::RELEASE_STAGE_CHECKED) { ?>
                <div class="release-warning"><h3>
                        This is a 'Checked' plugin release - Poggit reviewers consider it to be safe, but it has not yet passed advanced tests. Use at your own risk!
                </h3></div>
            <?php } ?>
            <div class="buildcount"><h4>From <a
                            href="<?= Poggit::getRootPath() ?>ci/<?= $this->release["author"] ?>/<?= urlencode($this->release["repo"]) ?>/<?= urlencode($this->projectName) ?>/<?= $this->buildInternal ?>">
                Dev Build #<?= $this->buildInternal ?></a> <?= $this->release["buildcreated"] ? " on " . htmlspecialchars(date('d M Y', $this->release["buildcreated"])) : "" ?>
                </h4></div>
            <?php if($this->releaseCompareURL != "") { ?>
                <div class="release-compare-link"><a target="_blank" href="<?= $this->releaseCompareURL ?>"><h4>
                            Compare <?= $this->lastReleaseClass ?>#<?= $this->lastReleaseInternal ?> - latest release
                            build</h4><?= EmbedUtils::ghLink("$this->releaseCompareURL") ?></a></div>
            <?php }
            if($this->buildCompareURL != "" && $this->buildCompareURL != $this->releaseCompareURL) { ?>
                <div class="release-compare-link"><a target="_blank" href="<?= $this->buildCompareURL ?>"><h4>
                            Compare <?= $this->lastBuildClass ?>#<?= $this->lastBuildInternal ?> - previous
                            build</h4><?= EmbedUtils::ghLink("$this->buildCompareURL") ?></a></div>
            <?php } ?>
            <div class="review-wrapper">
                <div class="plugin-table">
                    <div class="plugin-info-description">
                        <div class="release-description-header">
                            <div class="release-description">Plugin Description</div>
                            <?php if(SessionUtils::getInstance()->isLoggedIn()) { ?>
                                <div id="addreview" class="action review-release-button">Review This Release</div>
                            <?php } ?>
                        </div>
                        <div class="plugin-info">
                            <p><?php echo $this->description ?></p>
                            <br/>
                        </div>
                    </div>
                    <?php if($this->changelogText !== "") { ?>
                        <div class="plugin-info-changelog">
                            <div class="form-key">What's new</div>
                            <div class="plugin-info">
                                <p><?= $this->changelogText ?></p>
                            </div>
                        </div>
                    <?php } ?>
                    <div class="plugin-info-wrapper">
                        <div class="form-key">License</div>
                        <div class="plugin-info">
                            <p><?php echo $this->license ?? "None" ?></p>
                            <textarea readonly id="submit-customLicense" style="<?= $this->licenseDisplayStyle ?>"
                                      placeholder="Custom license content"
                                      rows="10"><?= $this->licenseText ?></textarea>
                        </div>
                    </div>
                    <div class="plugin-info-wrapper">
                        <div class="form-key">
                            <nobr>Pre-release</nobr>
                        </div>
                        <div class="plugin-info">
                            <p><?php echo $this->release["flags"] == PluginRelease::RELEASE_FLAG_PRE_RELEASE ? "Yes" : "No" ?></p>
                        </div>
                    </div>
                    <div class="plugin-info-wrapper">
                        <div class="form-key">Categories:</div>
                        <div class="plugin-info main-category"><?= PluginRelease::$CATEGORIES[$this->mainCategory] ?></div>
                        <?php foreach($this->categories as $id => $index) { ?>
                            <div class="plugin-info"><?= PluginRelease::$CATEGORIES[$index] ?></div>
                        <?php } ?>
                    </div>
                    <div class="plugin-info-wrapper">
                        <div class="form-key">Keywords</div>
                        <div class="plugin-info">
                            <p><?= $this->keywords ?></p>
                        </div>
                    </div>
                    <?php if(count($this->spoons) > 0) { ?>
                        <div class="plugin-info-wrapper">
                            <div class="form-key">Supported API versions</div>
                            <div class="plugin-info">
                                <script>
                                    var pocketMineApiVersions = <?= json_encode(PocketMineApi::$VERSIONS, JSON_UNESCAPED_SLASHES) ?>;
                                </script>
                                <table class="info-table" id="supportedSpoonsValue">
                                    <colgroup span="3"></colgroup>
                                    <tr>
                                        <th colspan="3" scope="colgroup"><em>API</em> Version</th>
                                    </tr>
                                    <?php foreach($this->spoons["since"] as $key => $since) { ?>
                                        <tr class="submit-spoonEntry">
                                            <td>
                                                <div class="submit-spoonVersion-from">
                                                    <div><?= $since ?></div>
                                                </div>
                                            </td>
                                            <td style="border:none;"> -</td>
                                            <td>
                                                <div class="submit-spoonVersion-to">
                                                    <div><?= ($this->spoons["till"][$key]) ?></div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </table>
                            </div>
                        </div>
                    <?php } ?>
                    <?php if(count($this->deps) > 0) { ?>
                        <div class="plugin-info-wrapper">
                            <div class="form-key">Dependencies</div>
                            <div class="plugin-info">
                                <table class="info-table" id="dependenciesValue">
                                    <tr>
                                        <th>Plugin name</th>
                                        <th>Compatible version</th>
                                        <th>Relevant Poggit release</th>
                                        <th>Required or optional?</th>
                                    </tr>
                                    <?php foreach($this->deps["name"] as $key => $name) { ?>
                                        <tr class="submit-depEntry">
                                            <td><input type="text" class="submit-depName" value="<?= $name ?>"
                                                       disabled/></td>
                                            <td><input type="text" class="submit-depVersion"
                                                       value="<?= $this->deps["version"][$key] ?>" disabled/></td>
                                            <td><span class="submit-depRelId" data-relId="0" data-projId="0"></span>
                                            </td>
                                            <td>
                                                <select class="submit-depSoftness" disabled>
                                                    <option value="hard" <?= $this->deps["isHard"][$key] == 1 ? "selected" : "" ?>
                                                            disabled>Required
                                                    </option>
                                                    <option value="soft" <?= $this->deps["isHard"][$key] == 0 ? "selected" : "" ?>
                                                            disabled>Optional
                                                    </option>
                                                </select>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </table>
                            </div>
                        </div>
                    <?php } ?>
                    <?php if(count($this->permissions) > 0) { ?>
                        <div class="plugin-info-wrapper">
                            <div class="form-key">Permissions</div>
                            <div class="plugin-info">
                                <div id="submit-perms" class="submit-perms-wrapper">
                                    <?php foreach($this->permissions as $reason => $perm) { ?>
                                        <div><?= htmlspecialchars(PluginRelease::$PERMISSIONS[$perm][0]) ?></div>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                    <?php if(count($this->reqr) > 0) { ?>
                        <div class="plugin-info-wrapper">
                            <div class="form-key">Requirements/<br/>Enhancements</div>
                            <div class="plugin-info">
                                <div id="submit-req">
                                    <table class="info-table" id="reqrValue">
                                        <tr>
                                            <th>Type</th>
                                            <th>Details</th>
                                            <th>Required?</th>
                                        </tr>
                                        <tr id="baseReqrForm" class="submit-reqrEntry" style="display: none;">
                                            <td>
                                                <select class="submit-reqrType" disabled>
                                                    <option value="mail">Mail server
                                                    </option>
                                                    <option value="mysql">MySQL database</option>
                                                    <option value="apiToken">Service API token
                                                    </option>
                                                    <option value="password">Passwords for services provided by the
                                                        plugin
                                                    </option>
                                                    <option value="other">Other</option>
                                                </select>
                                            </td>
                                            <td><input type="text" class="submit-reqrSpec" disabled/></td>
                                            <td>
                                                <select class="submit-reqrEnhc" disabled>
                                                    <option value="requirement">Requirement</option>
                                                    <option value="enhancement">Enhancement</option>
                                                </select>
                                            </td>
                                        </tr>
                                        <?php if(count($this->reqr) > 0) {
                                            foreach($this->reqr["type"] as $key => $type) { ?>
                                                <tr class="submit-reqrEntry">
                                                    <td>
                                                        <select class="submit-reqrType" disabled>
                                                            <option value="mail" <?= $type == 1 ? "selected" : "" ?>>
                                                                Mail server
                                                            </option>
                                                            <option value="mysql" <?= $type == 2 ? "selected" : "" ?>>
                                                                MySQL database
                                                            </option>
                                                            <option value="apiToken" <?= $type == 3 ? "selected" : "" ?>>
                                                                Service API token
                                                            </option>
                                                            <option value="password" <?= $type == 4 ? "selected" : "" ?>>
                                                                Passwords for services provided by the plugin
                                                            </option>
                                                            <option value="other" <?= $type == 5 ? "selected" : "" ?>>
                                                                Other
                                                            </option>
                                                        </select>
                                                    </td>
                                                    <td><input type="text" class="submit-reqrSpec"
                                                               value="<?= $this->reqr["details"][$key] ?>"/ disabled>
                                                    </td>
                                                    <td>
                                                        <select class="submit-reqrEnhc" disabled>
                                                            <option value="requirement" <?= $this->reqr["isRequire"][0] == 1 ? "selected" : "" ?>>
                                                                Requirement
                                                            </option>
                                                            <option value="enhancement" <?= $this->reqr["isRequire"][0] == 0 ? "selected" : "" ?>>
                                                                Enhancement
                                                            </option>
                                                        </select>
                                                    </td>
                                                </tr>
                                            <?php }
                                        } ?>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                </div>
                <div class="review-panel">
                    <?= Review::reviewPanel([$this->release["releaseId"]], SessionUtils::getInstance()->getLogin()["name"] ?? "") ?>
                </div>
            </div>

            <div class="bottomdownloadlink">
                <p>
                    <?php
                    $link = Poggit::getRootPath() . "r/" . $this->artifact . "/" . $this->projectName . ".phar";
                    ?>

                    <span class="action" onclick='window.location = <?= json_encode($link, JSON_UNESCAPED_SLASHES) ?>;'>
                                    Direct Download</span>
                </p>
            </div>
            <?php if($user == $this->release["author"] || Poggit::getAdmlv($user) === Poggit::ADM) { ?>
                <div class="deletereleasewrapper">
                    <h3>DELETE THIS RELEASE</h3>
                    WARNING: If you delete this release you will have to submit a new release, and the plugin will
                    start the entire review process again. If you wish to release a new version to replace this release,
                    please submit a new release for the new build BEFORE deleting this one so staff can compare changes.
                    If you are trying to hide a release you can EDIT it, and "Save as Draft" instead.
                    <span class="deleterelease" onclick="deleteRelease(this)" value="<?= $this->release["releaseId"] ?>"
                          author="<?= $this->release["author"] ?>">Delete This Release</span>
                </div>
            <?php } ?>
        </div>
        <?php $this->bodyFooter() ?>

        <!--            REVIEW STUFF-->
        <div id="dialog-form" title="Review <?= $this->projectName ?>">
            <form>
                <label author="author"><h3><?= $user ?></h3></label>
                <textarea id="reviewmessage"
                          maxlength="<?= Poggit::getAdmlv($user) >= Poggit::MODERATOR ? 1024 : 256 ?>" rows="3"
                          cols="20" class="reviewmessage"></textarea>
                <!-- Allow form submission with keyboard without duplicating the dialog button -->
                <input type="submit" tabindex="-1" style="position:absolute; top:-1000px">
            </form>
            <?php if(Poggit::getAdmlv($user) < Poggit::MODERATOR) { ?>
                <div class="reviewwarning"><p>You can leave one review per plugin release, and delete or update your
                        review at any time</p></div>
            <?php } ?>
            <form action="#">
                <label for="votes">Rate this Plugin </label>
                <select name="votes" id="votes">
                    <option>0</option>
                    <option>1</option>
                    <option>2</option>
                    <option selected>3</option>
                    <option>4</option>
                    <option>5</option>
                </select>
            </form>
            <?php
            if(Poggit::getAdmlv($user) >= Poggit::MODERATOR) { ?>
                <form action="#">
                    <label for="reviewcriteria">Criteria</label>
                    <select name="reviewcriteria" id="reviewcriteria">
                        <?php
                        $usedcrits = Review::getUsedCriteria($this->release["releaseId"], Review::getUIDFromName($user));
                        $usedcritslist = array_map(function ($usedcrit) {
                            return $usedcrit['criteria'];
                        }, $usedcrits);
                        foreach(PluginRelease::$CRITERIA_HUMAN as $key => $criteria) { ?>
                            <option value="<?= $key ?>" <?= in_array($key, $usedcritslist) ? "hidden='true'" : "selected" ?>><?= $criteria ?></option>
                        <?php } ?>
                    </select>
                </form>
            <?php } ?>
        </div>

        <script>
            var relId = <?= $this->release["releaseId"] ?>;

            $(function() {
                //$( "#votes" ).selectmenu();
                var dialog, form;

                function doAddReview() {
                    var criteria = $("#reviewcriteria").val();
                    var user = "<?= SessionUtils::getInstance()->getLogin()["name"] ?>";
                    var type = <?= Poggit::getAdmlv($user) >= Poggit::MODERATOR ? 1 : 2 ?>;
                    var cat = <?= $this->mainCategory ?>;
                    var score = $("#votes").val();
                    var message = $("#reviewmessage").val();
                    addReview(relId, user, criteria, type, cat, score, message);

                    dialog.dialog("close");
                    return true;
                }

                var modalPosition = {my: "center top", at: "center top+50", of: window};
                dialog = $("#dialog-form").dialog({
                    title: "Poggit Review",
                    autoOpen: false,
                    height: 380,
                    width: 250,
                    position: modalPosition,
                    modal: true,
                    buttons: {
                        Cancel: function() {
                            dialog.dialog("close");
                        },
                        "Post Review": doAddReview
                    },
                    open: function(event, ui) {
                        $('.ui-widget-overlay').bind('click', function() {
                            $("#dialog-form").dialog('close');
                        });
                    },
                    close: function() {
                        form[0].reset();
                    }
                });

                form = dialog.find("form").on("submit", function(event) {
                    event.preventDefault();
                    addReview();
                });

                $("#addreview").button().on("click", function() {
                    dialog.dialog("open");
                });
            });
        </script>
        </body>
        </html>
        <?php
    }
}
