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

namespace poggit\release\details;

use poggit\account\SessionUtils;
use poggit\ci\builder\ProjectBuilder;
use poggit\Mbd;
use poggit\module\Module;
use poggit\Poggit;
use poggit\release\PluginRelease;
use poggit\release\review\OfficialReviewModule as Review;
use poggit\resource\ResourceManager;
use poggit\utils\internet\MysqlUtils;
use poggit\utils\OutputManager;
use poggit\utils\PocketMineApi;

class ProjectReleasesModule extends Module {
    private $doStateReplace = false;
    private $release;

    private $projectName;
    private $name;
    private $shortDesc;
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
    private $artifact;

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
    private $changelogText;
    private $changelogType;
    private $totalupvotes;
    private $totaldownvotes;
    private $myvote;
    private $myvotemessage;

    public function getName(): string {
        return "release";
    }

    public function getAllNames(): array {
        return ["release", "rel", "plugin", "p"];
    }

    public function output() {
        $minifier = OutputManager::startMinifyHtml();
        $parts = array_filter(explode("/", $this->getQuery(), 2));
        $preReleaseCond = (!isset($_REQUEST["pre"]) or (isset($_REQUEST["pre"]) and $_REQUEST["pre"] != "off")) ? "(1 = 1)" : "((r.flags & 2) = 2)";
        $stmt = /** @lang MySQL */
            "SELECT r.releaseId, r.name, UNIX_TIMESTAMP(r.creation) AS created, b.sha, b.cause AS cause,  
                UNIX_TIMESTAMP(b.created) AS buildcreated, UNIX_TIMESTAMP(r.updateTime) AS stateupdated,
                r.shortDesc, r.version, r.artifact, r.buildId, r.licenseRes, artifact.type AS artifactType, artifact.dlCount AS dlCount, 
                r.description, descr.type AS descrType, r.icon,
                r.changelog, changelog.type AS changeLogType, r.license, r.flags, r.state, b.internal AS internal, b.class AS class,
                rp.owner AS author, rp.name AS repo, p.name AS projectName, p.projectId, p.path AS projectPath, p.lang AS hasTranslation,
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
        $session = SessionUtils::getInstance();
        $user = $session->getLogin()["name"] ?? "";
        $uid = $session->getLogin()["uid"] ?? "";

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
        //Votes
        $myvote = MysqlUtils::query("SELECT vote, message FROM release_votes WHERE releaseId = ? AND user = ?", "ii", $this->release["releaseId"], $uid);
        $this->myvote = (count($myvote) > 0) ? $myvote[0]["vote"] : 0;
        $this->myvotemessage = (count($myvote) > 0) ? $myvote[0]["message"] : "";
        $totalvotes = MysqlUtils::query("SELECT a.votetype, COUNT(a. votetype) as votecount
                    FROM (SELECT IF( rv.vote > 0,'upvotes','downvotes') as votetype from release_votes rv WHERE rv.releaseId = ?) as a
                    GROUP BY a. votetype", "i", $this->release["releaseId"]);
        foreach($totalvotes as $votes) {
            if($votes["votetype"] == "upvotes") {
                $this->totalupvotes = $votes["votecount"];
            } else {
                $this->totaldownvotes = $votes["votecount"];
            }
        }

        $this->state = (int) $this->release["state"];
        $isStaff = Poggit::getAdmlv($user) >= Poggit::MODERATOR;
        $isMine = strtolower($user) === strtolower($this->release["author"]);
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
        $this->descType = $this->release["desctype"] ? $this->release["desctype"] : "md";
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
            <meta property="article:author" content="<?= Mbd::esq($release["name"]) ?>"/>
            <meta property="article:section" content="Plugins"/>
            <?php $this->headIncludes($release["name"], $release["shortDesc"], "article", "", explode(" ", $this->keywords)) ?>
            <meta name="twitter:image:src" content="<?= Mbd::esq($this->icon ?? "") ?>">
        </head>
        <body>
        <?php $this->bodyHeader() ?>
        <div id="body">
            <div class="release-top">
                <?php
                $editLink = Poggit::getRootPath() . "update/" . $this->release["author"] . "/" . $this->release["repo"] . "/" . $this->projectName . "/" . $this->buildInternal;
                $user = SessionUtils::getInstance()->getLogin()["name"] ?? "";
                if($user == $this->release["author"] || Poggit::getAdmlv($user) >= Poggit::MODERATOR) { ?>
                    <div class="editrelease">
                        <span class="action" onclick="location.href='<?= Mbd::esq($editLink) ?>'">Edit Release</span>
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
                    <h3>
                        <a href="<?= Poggit::getRootPath() ?>ci/<?= $this->release["author"] ?>/<?= $this->release["repo"] ?>/<?= urlencode(
                            $this->projectName) ?>">
                            <?= htmlspecialchars($this->projectName) ?>
                            <?php
                            $tree = $this->release["sha"] ? ("tree/" . $this->release["sha"]) : "";
                            Mbd::ghLink("https://github.com/{$this->release["author"]}/{$this->release["repo"]}/$tree/{$this->release["projectPath"]}");
                            ?>
                        </a>
                    </h3>
                    <h4>by
                        <a href="<?= Poggit::getRootPath() . "ci/" . $this->release["author"] ?>"><?= $this->release["author"] ?></a>
                    </h4>
                </div>
                <div class="plugin-header-info">
                    <span id="releaseState"
                          class="plugin-state-<?= $this->state ?>"><?= htmlspecialchars(PluginRelease::$STAGE_HUMAN[$this->state]) ?></span>
                    <?php if($this->version !== "") { ?>
                        <div class="plugin-info">
                            Version<h5><?= htmlspecialchars($this->version) ?></h5>
                        </div>
                    <?php } ?>
                    <?php if($this->shortDesc !== "") { ?>
                        <div class="plugin-info">
                            <p>Summary <h5><?= htmlspecialchars($this->shortDesc) ?></h5></p>
                        </div>
                    <?php } ?></div>
                <div class="plugin-logo">
                    <?php if($this->icon === null) { ?>
                        <img src="<?= Poggit::getRootPath() ?>res/defaultPluginIcon2.png" height="128"/>
                    <?php } else { ?>
                        <img src="<?= Mbd::esq($this->icon) ?>" height="128"/>
                    <?php } ?>
                </div>
            </div>
            <?php if($this->state === PluginRelease::RELEASE_STAGE_CHECKED) { ?>
                <div class="release-warning"><h5>
                        This is a "Checked" version. Poggit reviewers found no obviously unsafe code, but it has not
                        been carefully tested yet. <i>Use at your own risk!</i>
                    </h5></div>
            <?php } ?>
            <div class="plugin-top">
                <div class="plugin-top-left">
                    <?php $link = Poggit::getRootPath() . "r/" . $this->artifact . "/" . $this->projectName . ".phar"; ?>
                    <div class="downloadrelease">
                        <p><a href="<?= $link ?>">
                            <span onclick='window.location = <?= json_encode($link, JSON_UNESCAPED_SLASHES) ?>;'
                                  class="action">Direct Download</span>
                            </a>
                            Open an old version:
                            <select id="releaseVersionHistory"
                                    onchange='window.location = getRelativeRootPath() + "p/" + <?= json_encode($this->release["name"]) ?> + "/" + this.value;'>
                                <?php foreach(MysqlUtils::query("SELECT version, state, UNIX_TIMESTAMP(updateTime) AS updateTime
                                    FROM releases WHERE projectId = ? ORDER BY creation DESC",
                                    "i", $this->release["projectId"]) as $row) {
                                    if(!$isMine && !$isStaff && $row["state"] < PluginRelease::MIN_PUBLIC_RELSTAGE) continue;
                                    ?>
                                    <option value="<?= htmlspecialchars($row["version"], ENT_QUOTES) ?>"
                                        <?= $row["version"] === $this->release["version"] ? "selected" : "" ?>
                                    ><?= htmlspecialchars($row["version"]) ?>,
                                        <?= PluginRelease::$STAGE_HUMAN[$row["state"]] ?> on
                                        <?= date('d M Y', $row["updateTime"]) ?> </option>
                                <?php } ?>
                            </select>
                        </p>
                    </div>
                    <div class="buildcount"><h6>
                            Submitted on <?= htmlspecialchars(date('d M Y', $this->release["created"])) ?>,
                            <?= PluginRelease::$STAGE_HUMAN[$this->state] ?> on
                            <?= htmlspecialchars(date('d M Y', $this->release["stateupdated"])) ?>
                            from
                            <a href="<?= Poggit::getRootPath() ?>ci/<?= $this->release["author"] ?>/<?= urlencode($this->release["repo"]) ?>/<?= urlencode($this->projectName) ?>/<?= $this->buildInternal ?>">
                                Dev Build #<?= $this->buildInternal ?></a>
                        </h6></div>
                    <?php if($this->releaseCompareURL != "") { ?>
                        <div class="release-compare-link"><a target="_blank" href="<?= $this->releaseCompareURL ?>"><h6>
                                    Compare <?= $this->lastReleaseClass ?>#<?= $this->lastReleaseInternal ?> - latest
                                    release build</h6> <?php Mbd::ghLink($this->releaseCompareURL) ?></a></div>
                    <?php }
                    if($this->buildCompareURL != "" && $this->buildCompareURL != $this->releaseCompareURL) { ?>
                        <!-- I think this is useless
                        <div class="release-compare-link"><a target="_blank" href="<?= $this->buildCompareURL ?>"><h6>
                                    Compare <?= $this->lastBuildClass ?>#<?= $this->lastBuildInternal ?> - previous
                                    build</h6><?php Mbd::ghLink($this->buildCompareURL) ?></a></div>-->
                    <?php } ?>
                </div>
                <?php if(count($this->spoons) > 0) { ?>
                    <div class="plugin-info-wrapper">
                        <div class="form-key">Supported API versions</div>
                        <div class="plugin-info">
                            <script>
                                var pocketMineApiVersions = <?= json_encode(PocketMineApi::$VERSIONS, JSON_UNESCAPED_SLASHES) ?>;
                            </script>
                            <table class="info-table" id="supportedSpoonsValue">
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
                        <div class="form-key">Related Plugins</div>
                        <div class="plugin-info">
                            <table class="info-table" id="dependenciesValue">
                                <?php foreach($this->deps["name"] as $key => $name) {
                                    $link = Poggit::getRootPath() . "p/" . $name . "/" . $this->deps["version"][$key];
                                    ?>
                                    <tr>
                                        <td><span type="text"
                                                  class="submit-depName"><?= $name ?> <?= $this->deps["version"][$key] ?></span>
                                        </td>
                                        <td>
                                            <span> <?= $this->deps["isHard"][$key] == 1 ? "Required" : "Optional" ?></span>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-default btn-sm text-center"><a
                                                        href="<?= $link ?>">View Plugin</a>
                                            </button>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </table>
                        </div>
                    </div>
                <?php } ?>
            </div>
            <div class="review-wrapper">
                <div class="plugin-table">
                    <div class="plugin-info-description">
                        <div class="release-description-header">
                            <div class="release-description">Plugin Description</div>
                            <?php if($this->release["state"] == PluginRelease::RELEASE_STAGE_CHECKED) { ?>
                                <div id="upvote" class="upvotes<?= $session->isLoggedIn() ? " vote-button" : "" ?>"><img
                                            src='<?= Poggit::getRootPath() ?>res/voteup.png'><?= $this->totalupvotes ?? "0" ?>
                                </div>
                                <div id="downvote" class="downvotes<?= $session->isLoggedIn() ? " vote-button" : "" ?>">
                                    <img
                                            src='<?= Poggit::getRootPath() ?>res/votedown.png'><?= $this->totaldownvotes ?? "0" ?>
                                </div>
                            <?php } ?>
                            <?php if(SessionUtils::getInstance()->isLoggedIn() && !$isMine) { ?>
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
                    <a href="<?= $link ?>">
                    <span class="action" onclick='window.location = <?= json_encode($link, JSON_UNESCAPED_SLASHES) ?>;'>
                        Direct Download</span></a>
                </p>
            </div>
        </div>
        <?php $this->bodyFooter() ?>
        <?php if(!$isMine) { ?>
            <!-- REVIEW DIALOGUE -->
            <div id="review-dialog" title="Review <?= $this->projectName ?>">
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
        <?php } ?>
        <?php if($session->isLoggedIn() && $this->release["state"] == PluginRelease::RELEASE_STAGE_CHECKED) { ?>
            <!-- VOTING DIALOGUES -->
            <div id="voteup-dialog" title="Voting <?= $this->projectName ?>">
                <form>
                    <label plugin="plugin"><h4><?= $this->projectName ?></h4></label>
                    <?php if($this->myvote > 0) { ?>
                        <label><h6>You have already voted to ACCEPT this plugin</h6></label>
                    <?php } elseif($this->myvote < 0) { ?>
                        <label><h6>You previously voted to REJECT this plugin</h6></label>
                        <label><h6>Click below to change your vote</h6></label>
                    <?php } else { ?>
                        <label <h6>Poggit users can vote to accept or reject 'Checked' plugins</h6></label>
                        <label <h6>Please click 'Accept' to accept this plugin</h6></label>
                    <?php } ?>
                    <!-- Allow form submission with keyboard without duplicating the dialog button -->
                    <input type="submit" tabindex="-1" style="position:absolute; top:-1000px">
                </form>
            </div>
            <div id="votedown-dialog" title="Voting <?= $this->projectName ?>">
                <form>
                    <label plugin="plugin"><h4><?= $this->projectName ?></h4></label>
                    <?php if($this->myvote > 0) { ?>
                        <label><h6>You previously voted to ACCEPT this plugin</h6></label>
                        <label><h6>Click below to REJECT, and leave a short reason</h6></label>
                    <?php } elseif($this->myvote < 0) { ?>
                        <label><h6>You have already voted to REJECT this plugin</h6></label>
                        <label><h6>Click below to confirm and update the reason</h6></label>
                    <?php } else { ?>
                        <label <h6>Poggit users can vote to accept or reject 'Checked' plugins</h6></label>
                        <label <h6>Please click 'REJECT' to reject this plugin, and leave a short reason
                            below</h6></label>
                    <?php } ?>
                    <textarea id="votemessage"
                              maxlength="255" rows="3"
                              cols="20" class="votemessage"><?= $this->myvotemessage ?? "" ?></textarea>
                    <!-- Allow form submission with keyboard without duplicating the dialog button -->
                    <input type="submit" tabindex="-1" style="position:absolute; top:-1000px">
                    <label id="vote-error" class="vote-error"></label>
                </form>
            </div>
        <?php } ?>
        <script>
            var relId = <?= $this->release["releaseId"] ?>;
            var modalPosition = {my: "center top", at: "center top+100", of: window};

            <?php if (!$isMine){ ?>
            var reviewdialog, reviewform;
            // REVIEWING
            function doAddReview() {
                var criteria = $("#reviewcriteria").val();
                var user = "<?= SessionUtils::getInstance()->getLogin()["name"] ?>";
                var type = <?= Poggit::getAdmlv($user) >= Poggit::MODERATOR ? 1 : 2 ?>;
                var cat = <?= $this->mainCategory ?>;
                var score = $("#votes").val();
                var message = $("#reviewmessage").val();
                addReview(relId, user, criteria, type, cat, score, message);

                reviewdialog.dialog("close");
                return true;
            }

            reviewdialog = $("#review-dialog").dialog({
                title: "Poggit Review",
                autoOpen: false,
                height: 380,
                width: 250,
                position: modalPosition,
                modal: true,
                buttons: {
                    Cancel: function() {
                        reviewdialog.dialog("close");
                    },
                    "Post Review": doAddReview
                },
                open: function(event, ui) {
                    $('.ui-widget-overlay').bind('click', function() {
                        $("#review-dialog").dialog('close');
                    });
                },
                close: function() {
                    reviewform[0].reset();
                }
            });

            reviewform = reviewdialog.find("form").on("submit", function(event) {
                event.preventDefault();
            });

            $("#addreview").button().on("click", function() {
                reviewdialog.dialog("open");
            });
            <?php } ?>
            $(function() {
                var voteupdialog, voteupform, votedowndialog, votedownform;

                <?php if ($session->isLoggedIn() && $this->release["state"] == PluginRelease::RELEASE_STAGE_CHECKED) { ?>
                // VOTING
                function doUpVote() {
                    var message = $("#votemessage").val();
                    var vote = 1;
                    addVote(relId, vote, message);
                    voteupdialog.dialog("close");
                    return true;
                }

                function doDownVote() {
                    var message = $("#votemessage").val();
                    if(message.length < 10) {
                        $("#vote-error").text("Please type at least 10 characters...");
                        return;
                    }
                    var vote = -1;
                    addVote(relId, vote, message);
                    votedowndialog.dialog("close");
                    return true;
                }

                voteupdialog = $("#voteup-dialog").dialog({
                    title: "ACCEPT Plugin",
                    autoOpen: false,
                    height: 300,
                    width: 250,
                    position: modalPosition,
                    modal: true,
                    buttons: {
                        Cancel: function() {
                            voteupdialog.dialog("close");
                        },
                        <?php if($this->myvote <= 0) { ?>"Accept": doUpVote<?php } ?>
                    },
                    open: function(event, ui) {
                        $('.ui-widget-overlay').bind('click', function() {
                            $("#voteup-dialog").dialog('close');
                        });
                    },
                    close: function() {
                        voteupform[0].reset();
                    }
                });
                voteupform = voteupdialog.find("form").on("submit", function(event) {
                    event.preventDefault();
                });

                $("#upvote").button().on("click", function() {
                    voteupdialog.dialog("open");
                });

                votedowndialog = $("#votedown-dialog").dialog({
                    title: "REJECT Plugin",
                    autoOpen: false,
                    height: 380,
                    width: 250,
                    position: modalPosition,
                    modal: true,
                    buttons: {
                        Cancel: function() {
                            votedowndialog.dialog("close");
                        },
                        "Reject": doDownVote
                    },
                    open: function(event, ui) {
                        $('.ui-widget-overlay').bind('click', function() {
                            $("#votedown-dialog").dialog('close');
                        });
                    },
                    close: function() {
                        votedownform[0].reset();
                    }
                });
                votedownform = votedowndialog.find("form").on("submit", function(event) {
                    event.preventDefault();
                });

                $("#downvote").button().on("click", function() {
                    votedowndialog.dialog("open");
                });
                <?php } ?>
            });
        </script>
        </body>
        </html>
        <?php
        OutputManager::endMinifyHtml($minifier);
    }
}
