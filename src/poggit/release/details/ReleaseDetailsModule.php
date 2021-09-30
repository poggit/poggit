<?php

/*
 * Poggit
 *
 * Copyright (C) 2016-2018 Poggit
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

use poggit\account\Session;
use poggit\ci\builder\ProjectBuilder;
use poggit\Config;
use poggit\Mbd;
use poggit\Meta;
use poggit\module\HtmlModule;
use poggit\module\Module;
use poggit\release\details\review\PluginReview;
use poggit\release\PluginRequirement;
use poggit\release\Release;
use poggit\resource\ResourceManager;
use poggit\utils\internet\GitHub;
use poggit\utils\internet\Mysql;
use poggit\utils\lang\Lang;
use poggit\utils\OutputManager;
use function array_map;
use function asort;
use function assert;
use function count;
use function date;
use function explode;
use function file_get_contents;
use function htmlspecialchars;
use function implode;
use function in_array;
use function json_decode;
use function json_encode;
use function ksort;
use function round;
use function strlen;
use function strtolower;
use function urlencode;
use const DATE_ATOM;
use const ENT_QUOTES;
use const JSON_UNESCAPED_SLASHES;
use const SORT_NUMERIC;
use const SORT_STRING;

class ReleaseDetailsModule extends HtmlModule {
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
    private $assignee;
    private $keywords;
    private $categories;
    private $mainCategory;
    private $spoons;
    private $permissions;
    private $deps;
    private $assocs;
    private $parentRelease;
    private $reqr;
    private $descType;
    private $icon;
    private $state;
    private $artifact;

    private $buildInternal;
    private $thisReleaseCommit;
    private $releaseCompareURL;
    private $previousApprovedReleaseCreated;
    private $previousApprovedReleaseInternal;
    private $previousApprovedReleaseClass;
    private $previousApprovedReleaseCommit;
    private $previousApprovedReleaseVersion;
    private $changelogData = null;
    private $upVotes = [];
    private $downVotes = [];
    private $myVote;
    private $myVoteMessage;
    private $authors;
    private $releaseStats;
    private $visibleReleases;

    public function output() {
        $session = Session::getInstance();
        $user = $session->getName();
        $uid = $session->getUid();
        $isStaff = Meta::getAdmlv($user) >= Meta::ADMLV_MODERATOR;
        $isReviewer = Meta::getAdmlv($user) >= Meta::ADMLV_REVIEWER;

        $minifier = OutputManager::startMinifyHtml();
        $parts = Lang::explodeNoEmpty("/", $this->getQuery(), 2);
        $preReleaseCond = (!isset($_REQUEST["pre"]) or (isset($_REQUEST["pre"]) and $_REQUEST["pre"] !== "off")) ? "(1 = 1)" : "((r.flags & 2) = 2)";
        $stmt = /** @lang MySQL */
            "SELECT r.releaseId, r.name, UNIX_TIMESTAMP(r.creation) created, b.sha, b.cause cause,  
                UNIX_TIMESTAMP(b.created) buildCreated, UNIX_TIMESTAMP(r.updateTime) stateUpdated,
                r.shortDesc, r.version, r.artifact, r.buildId, r.licenseRes, artifact.type artifactType, artifact.dlCount dlCount,
                r.description, descr.type descrType, r.icon, r.parent_releaseId, ass.name assignee,
                r.license, r.flags, r.state, b.internal internal, b.class class,
                rp.owner author, rp.name repo, p.name projectName, p.projectId, p.path projectPath, p.lang hasTranslation
                FROM releases r
                INNER JOIN projects p ON r.projectId = p.projectId
                INNER JOIN repos rp ON p.repoId = rp.repoId
                INNER JOIN resources artifact ON r.artifact = artifact.resourceId
                INNER JOIN resources descr ON r.description = descr.resourceId
                INNER JOIN builds b ON r.buildId = b.buildId
                LEFT JOIN users ass ON r.assignee = ass.uid
                WHERE r.name = ? AND $preReleaseCond ORDER BY b.created DESC";
        if(count($parts) === 0) Meta::redirect("plugins");
        if(count($parts) === 1) {
            $author = null;
            $name = $parts[0];
            $projects = Mysql::query($stmt, "s", $name);
            if(count($projects) === 0) Meta::redirect("plugins?term=" . urlencode($name) . "&error=" . urlencode("No plugins called $name"));
            $ownerOrStaff = $isStaff || strtolower($user) === strtolower($projects[0]["author"]);
            $minVisibleState = $ownerOrStaff ? Release::STATE_DRAFT : Release::STATE_VOTED;
            $i = 0;
            foreach($projects as $project) {
                if($project["state"] >= $minVisibleState) {
                    $release = $project;
                    break;
                }
                $i++;
            }
            if(!isset($release)) {
                Meta::redirect("plugins?term=" . urlencode($name));
                return;
            }
            $this->thisReleaseCommit = json_decode($projects[$i]["cause"])->commit;
            if(($projectCount = count($projects)) - $i > 1) {
                for($j = $i + 1; $j < $projectCount; $j++) { // Get data for the next release visible to this user
                    if(isset($projects[$j]) && ($projects[$j]["state"] > $release["state"])) {
                        $this->previousApprovedReleaseInternal = (int) $projects[$j]["internal"];
                        $this->previousApprovedReleaseClass = ProjectBuilder::$BUILD_CLASS_HUMAN[$projects[$j]["class"]];
                        $this->previousApprovedReleaseCreated = (int) $projects[$j]["buildCreated"];
                        $this->previousApprovedReleaseCommit = json_decode($projects[$j]["cause"])->commit;
                        $this->previousApprovedReleaseVersion = $projects[$j]["version"];
                        break;
                    }
                }
            }
        } else {
            assert(count($parts) === 2);
            list($name, $requestedVersion) = $parts;
            $projects = Mysql::query($stmt, "s", $name);

            if(count($projects) > 1) {
                $ownerOrStaff = $isStaff || strtolower($user) === strtolower($projects[0]["author"]);
                $minVisibleState = $ownerOrStaff ? Release::STATE_DRAFT : ($session->isLoggedIn() ? Release::STATE_CHECKED : Release::STATE_VOTED);
                $minVisibleCommit = $ownerOrStaff ? Release::STATE_DRAFT : Release::STATE_VOTED;
                $i = 0;
                $found = false;
                foreach($projects as $project) {
                    if($project["version"] === $requestedVersion || $found) {
                        if(!($project["state"] >= $minVisibleState)) {
                            $i++;
                            continue;
                        }
                        if(!$found) {
                            $release = $project;
                            $this->thisReleaseCommit = json_decode($projects[$i]["cause"])->commit;
                            $found = true;
                            if(($projectCount = count($projects)) - $i > 1) {
                                for($j = $i + 1; $j < $projectCount; $j++) { // Get data for the next release visible to this user
                                    if(isset($projects[$j]) && ($projects[$j]["state"] > $release["state"])) {
                                        $this->previousApprovedReleaseInternal = (int) $projects[$j]["internal"];
                                        $this->previousApprovedReleaseClass = ProjectBuilder::$BUILD_CLASS_HUMAN[$projects[$j]["class"]];
                                        $this->previousApprovedReleaseCreated = (int) $projects[$j]["buildCreated"];
                                        $this->previousApprovedReleaseCommit = json_decode($projects[$j]["cause"])->commit;
                                        $this->previousApprovedReleaseVersion = $projects[$j]["version"];
                                        break;
                                    }
                                }
                            }
                        }
                    }
                    $i++;
                }
                $this->doStateReplace = true;
                if(!isset($release)) Meta::redirect((count($projects) > 0 ? "p/" : "plugins?term=") . urlencode($name));
            } else {
                if(count($projects) > 0) { // exactly 1 result
                    $release = $projects[0];
                } else {
                    Meta::redirect("plugins?term=" . urlencode($name));
                }
            }
        }

        /** @var array $release */
        $this->release = $release;
        $isMine = strtolower($user) === strtolower($this->release["author"]);

        $releaseRows = Mysql::query("SELECT version, state, UNIX_TIMESTAMP(updateTime) AS updateTime
                                    FROM releases WHERE projectId = ? ORDER BY creation DESC",
            "i", $this->release["projectId"]);
        foreach($releaseRows as $row) {
            if(!$isMine && !$isStaff && $row["state"] < Config::MIN_PUBLIC_RELEASE_STATE) continue;
            $this->visibleReleases[] = $row;

        }
        $this->releaseCompareURL = ($this->thisReleaseCommit && $this->previousApprovedReleaseCommit && ($this->previousApprovedReleaseCreated < $this->release["buildCreated"])) ? "http://github.com/" . urlencode($this->release["author"]) . "/" .
            urlencode($this->release["repo"]) . "/compare/" . $this->previousApprovedReleaseCommit . "..." . $this->thisReleaseCommit : "";

        $this->release["description"] = (int) $this->release["description"];
        $descType = Mysql::query("SELECT type FROM resources WHERE resourceId = ? LIMIT 1", "i", $this->release["description"]);
        $this->release["descType"] = $descType[0]["type"];
        $this->release["releaseId"] = (int) $this->release["releaseId"];
        $this->release["buildId"] = (int) $this->release["buildId"];
        $this->release["internal"] = (int) $this->release["internal"];
        // Changelog
        $changeLogRows = Mysql::query("SELECT r.changelog as changelog FROM resources res 
        INNER JOIN releases r ON (r.releaseId = res.resourceId OR res.resourceId = '1') AND r.releaseId = ? ORDER BY type DESC", "i", $release["releaseId"]);
        $this->release["changelog"] = (int) $changeLogRows[0]["changelog"];
        if($this->release["changelog"] !== ResourceManager::NULL_RESOURCE && $this->release["changelog"] !== 0) {
            $clTypeRow = Mysql::query("SELECT type FROM resources WHERE resourceId = ? LIMIT 1", "i", $this->release["changelog"]);
            $this->release["changelogType"] = $clTypeRow[0]["type"] ?? null;
        } else {
            $this->release["changelog"] = null;
            $this->release["changelogType"] = null;
        }
        // Keywords
        $keywordRow = Mysql::query("SELECT word FROM release_keywords WHERE projectId = ?", "i", $this->release["projectId"]);
        $this->release["keywords"] = [];
        foreach($keywordRow as $row) {
            $this->release["keywords"][] = $row["word"];
        }
        // Categories
        $categoryRow = Mysql::query("SELECT category, isMainCategory FROM release_categories WHERE projectId = ?", "i", $this->release["projectId"]);
        $this->release["categories"] = [];
        $this->release["mainCategory"] = 1;
        foreach($categoryRow as $row) {
            if($row["isMainCategory"] === "\1" || $row["isMainCategory"] === "1" || $row["isMainCategory"] === 1) {
                $this->release["mainCategory"] = (int) $row["category"];
            } else {
                $this->release["categories"][] = (int) $row["category"];
            }
        }
        // Spoons
        $this->release["spoons"] = [];
        $spoons = Mysql::query("SELECT since, till FROM release_spoons WHERE releaseId = ?", "i", $this->release["releaseId"]);
        if(count($spoons) > 0) {
            foreach($spoons as $row) {
                $this->release["spoons"]["since"][] = $row["since"];
                $this->release["spoons"]["till"][] = $row["till"];
            }
        }
        //Permissions
        $this->release["permissions"] = [];
        $perms = Mysql::query("SELECT val FROM release_perms WHERE releaseId = ?", "i", $this->release["releaseId"]);
        if(count($perms) > 0) {
            foreach($perms as $row) {
                $this->release["permissions"][] = $row["val"];
            }
        }
        // Associated
        $this->release["assocs"] = [];
        $this->parentRelease = Mysql::query("SELECT releaseId, name, version, artifact FROM releases WHERE releaseId = ?", "i", $this->release["parent_releaseId"])[0] ?? null;
        if($this->parentRelease) {
            $this->release["assocs"]["name"][] = $this->parentRelease["name"];
            $this->release["assocs"]["version"][] = $this->parentRelease["version"];
            $this->release["assocs"]["artifact"][] = $this->parentRelease["artifact"];
            $this->release["assocs"]["parent"][] = true;
        }
        $assocs = Mysql::query("SELECT releaseId, name, version, artifact FROM releases WHERE parent_releaseId = ? AND releaseId !=?", "ii", $this->parentRelease["releaseId"] ?? $this->release["releaseId"], $this->release["releaseId"]);
        if(count($assocs) > 0) {
            foreach($assocs as $row) {
                $this->release["assocs"]["name"][] = $row["name"];
                $this->release["assocs"]["version"][] = $row["version"];
                $this->release["assocs"]["artifact"][] = $row["artifact"];
                $this->release["assocs"]["parent"][] = false;
            }
        }
        // Dependencies
        $this->release["deps"] = [];
        $deps = Mysql::query("SELECT name, version, depRelId, IF(isHard, 1, 0) isHard FROM release_deps WHERE releaseId = ?", "i", $this->release["releaseId"]);
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
        $reqr = Mysql::query("SELECT type, details, IF(isRequire, 1, 0) isRequire FROM release_reqr WHERE releaseId = ?", "i", $this->release["releaseId"]);
        if(count($reqr) > 0) {
            foreach($reqr as $row) {
                $this->release["reqr"]["type"][] = $row["type"];
                $this->release["reqr"]["details"][] = $row["details"];
                $this->release["reqr"]["isRequire"][] = (int) $row["isRequire"];
            }
        }
        //Votes
        $myVote = Mysql::query("SELECT vote, message FROM release_votes WHERE releaseId = ? AND user = ?", "ii", $this->release["releaseId"], $uid);
        $this->myVote = (count($myVote) > 0) ? $myVote[0]["vote"] : 0;
        $this->myVoteMessage = (count($myVote) > 0) ? $myVote[0]["message"] : "";

        $this->releaseStats = Release::getReleaseStats($this->release["releaseId"], $this->release["projectId"], $this->release["created"]);

        foreach(Mysql::query("SELECT u.name AS user FROM release_votes rv
INNER JOIN users u ON rv.user = u.uid WHERE  rv.releaseId = ? and rv.vote = 1", "i", $this->release["releaseId"]) as $row) {
            $this->upVotes[] = $row["user"];
        }
        foreach(Mysql::query("SELECT u.name AS user FROM release_votes rv
INNER JOIN users u ON rv.user = u.uid WHERE  rv.releaseId = ? and rv.vote = -1", "i", $this->release["releaseId"]) as $row) {
            $this->downVotes[] = $row["user"];
        }

        $this->state = (int) $this->release["state"];
        $writePerm = GitHub::testPermission($this->release["author"] . "/" . $this->release["repo"], $session->getAccessToken(), $session->getName(), "push");
        if((($this->state < Config::MIN_PUBLIC_RELEASE_STATE && !$session->isLoggedIn()) || ($this->state < Release::STATE_CHECKED && $session->isLoggedIn())) && (!$isMine && !$isStaff)) {
            Meta::redirect("plugins?term=" . urlencode($name) . "&error=" . urlencode("You don't have permission to view this plugin yet."));
        }
        $this->projectName = $this->release["projectName"];
        $this->name = $this->release["name"];
        $this->buildInternal = $this->release["internal"];
        $this->description = $this->release["description"] ? file_get_contents(ResourceManager::getInstance()->getResource($this->release["description"])) : "No Description";
        $this->version = $this->release["version"];
        $this->shortDesc = $this->release["shortDesc"];
        $this->licenseDisplayStyle = ($this->release["license"] === "custom") ? "display: true" : "display: none";
        $this->licenseText = $this->release["licenseRes"] ? file_get_contents(ResourceManager::getInstance()->getResource($this->release["licenseRes"])) : "";
        $this->license = $this->release["license"];
        if($this->release["changelog"]) {
            $rows = Mysql::query("SELECT version, resourceId, type FROM releases INNER JOIN resources ON resourceId = changelog
                WHERE projectId = ? AND state >= ? AND releaseId <= ? ORDER BY releaseId DESC",
                "iii", $this->release["projectId"], $isMine || $isStaff ? Release::STATE_SUBMITTED : Config::MIN_PUBLIC_RELEASE_STATE, $this->release["releaseId"]);
            foreach($rows as $row) {
                $this->changelogData[$row["version"]] = ((int) $row["resourceId"]) === ResourceManager::NULL_RESOURCE ? [
                    "text" => "Initial version",
                    "type" => "init"
                ] : [
                    "text" => file_get_contents(ResourceManager::pathTo((int) $row["resourceId"], $row["type"])),
                    "type" => $row["type"],
                ];
            }
        }
        $this->assignee = $this->release["assignee"];
        $this->keywords = $this->release["keywords"] ? implode(" ", $this->release["keywords"]) : "";
        $this->categories = $this->release["categories"] ?: [];
        $this->authors = [];
        foreach(Mysql::query("SELECT uid, name, level FROM release_authors WHERE projectId = ?",
            "i", $this->release["projectId"]) as $row) {
            $this->authors[(int) $row["level"]][(int) $row["uid"]] = $row["name"];
        }
        ksort($this->authors, SORT_NUMERIC);
        foreach($this->authors as $level => $authors) {
            asort($this->authors[$level], SORT_STRING);
        }
        $this->spoons = $this->release["spoons"] ?: [];
        $this->permissions = $this->release["permissions"] ?: [];
        $this->deps = $this->release["deps"] ?: [];
        $this->assocs = $this->release["assocs"] ?: [];
        $this->reqr = $this->release["reqr"] ?: [];
        $this->mainCategory = $this->release["mainCategory"] ?: 1;
        $this->descType = $this->release["descType"] ?: "md";
        $this->icon = $this->release["icon"];
        $this->artifact = (int) $this->release["artifact"];

        $earliestDate = (int) Mysql::query("SELECT MIN(UNIX_TIMESTAMP(creation)) AS created FROM releases WHERE projectId = ?",
            "i", (int) $release["projectId"])[0]["created"];
//        $tags = Poggit::queryAndFetch("SELECT val FROM release_meta WHERE releaseId = ? AND type = ?", "ii", (int) $release["releaseId"], (int)ReleaseConstants::TYPE_CATEGORY);
        ?>
      <html>
      <head
          prefix="og: http://ogp.me/ns# fb: http://ogp.me/ns/fb# object: http://ogp.me/ns/object# article: http://ogp.me/ns/article# profile: http://ogp.me/ns/profile#">
        <title><?= htmlspecialchars($release["name"] . " v{$this->version}" . " by " . $release["author"]) ?></title>
        <meta property="article:published_time" content="<?= date(DATE_ATOM, $earliestDate) ?>"/>
        <meta property="article:modified_time" content="<?= date(DATE_ATOM, (int) $release["created"]) ?>"/>
        <meta property="article:author" content="<?= Mbd::esq($release["name"]) ?>"/>
        <meta property="article:section" content="Plugins"/>
          <?php
          $this->headIncludes($release["name"] . " v" . $release["version"], $release["shortDesc"], "article", "", explode(" ", $this->keywords), $this->icon !== null ? Mbd::esq($this->icon) : null);
          Module::includeCss("jquery.verticalTabs.min");
          ?>
        <meta name="twitter:image:src"
              content="<?= Mbd::esq($this->icon ?? "https://poggit.pmmp.io/res/defaultPluginIcon2.png") ?>">
          <?php
          $releaseDetails = [
              "releaseId" => $this->release["releaseId"],
              "name" => $this->name,
              "version" => $this->version,
              "mainCategory" => $this->release["mainCategory"],
              "state" => $this->release["state"],
              "created" => $this->release["created"],
              "project" => [
                  "repo" => [
                      "owner" => $this->release["author"],
                      "name" => $this->release["repo"]
                  ],
                  "path" => $this->release["projectPath"],
                  "name" => $this->release["projectName"]
              ],
              "build" => [
                  "buildId" => $this->release["buildId"],
                  "internal" => $this->release["internal"],
                  "sha" => $this->release["sha"],
                  "tree" => $this->release["sha"] ? "tree/{$this->release["sha"]}/" : "",
              ],
              "rejectPath" => "repos/{$this->release["author"]}/{$this->release["repo"]}/commits/{$this->release["sha"]}/comments",
              "isMine" => $isMine,
              "myVote" => $this->myVote,
              "myVoteMessage" => $this->myVoteMessage,
          ];
          ?>
        <script>var releaseDetails = <?= json_encode($releaseDetails, JSON_UNESCAPED_SLASHES) ?>;</script>
      </head>
      <body>
      <?php $this->bodyHeader() ?>
      <div id="body">
        <div class="release-top">
            <?php
            $editLink = Meta::root() . "update/" . $this->release["author"] . "/" . $this->release["repo"] . "/" . $this->projectName . "/" . $this->buildInternal;
            $user = Session::getInstance()->getName();
            if($writePerm || $isStaff) { ?>
              <div class="release-edit">
                        <span class="action-red"
                              onclick="$('#wait-spinner').modal();location.href='<?= Mbd::esq($editLink) ?>'">Edit Release</span>
              </div>
            <?php } ?>
          <div id="release-admin-marker"></div>
            <?php if(Meta::getAdmlv() >= Meta::ADMLV_REVIEWER) Module::queueJs("release.details.admin"); ?>
        </div>
        <div class="plugin-heading">
          <div class="plugin-title">
            <h3>
              <nobr>
                  <?php
                  if($this->parentRelease !== null && $this->parentRelease["name"] !== null) { ?>
                <a href="<?= Meta::root() ?>p/<?= $this->parentRelease["name"] ?>/<?= $this->parentRelease["version"] ?>">
                    <?= $this->parentRelease["name"] ? htmlspecialchars($this->parentRelease["name"]) . " > " : "" ?>
                    <?php } ?>
                  <a href="<?= Meta::root() ?>ci/<?= $this->release["author"] ?>/<?= $this->release["repo"] ?>/<?= urlencode(
                      $this->projectName) ?>">
                      <?= htmlspecialchars($this->release["name"]) ?>
                  </a>
                    <?php Release::printFlags($this->release["flags"], $this->release["name"]) ?>
                  <?php
                  $tree = $this->release["sha"] ? ("tree/" . $this->release["sha"]) : "";
                  Mbd::ghLink("https://github.com/{$this->release["author"]}/{$this->release["repo"]}/$tree/{$this->release["projectPath"]}");
                  ?></nobr>
            </h3>
            <h4>by
              <a href="<?= Meta::root() . "plugins/by/" . $this->release["author"] ?>"><?= $this->release["author"] ?></a>
            </h4>
          </div>
          <div class="plugin-logo">
              <?php
              if($this->icon === null || !$session->showsIcons()) { ?>
                <img src="<?= Meta::root() ?>res/defaultPluginIcon2.png" height="64"/>
              <?php } else { ?>
                <img src="<?= Mbd::esq($this->icon) ?>" height="64" onerror="this.src = '/res/defaultPluginIcon3.png'; this.onerror = null;"/>
              <?php } ?>
          </div>
          <div class="plugin-header-info">
              <?php if($this->shortDesc !== "") { ?>
                <div class="plugin-info">
                  <h5><?= htmlspecialchars($this->shortDesc) ?></h5>
                    <?php if($this->version !== "") { ?>
                      <h6>version <?= htmlspecialchars($this->version) ?></h6>
                      <span id="releaseState"
                            class="plugin-state-<?= $this->state ?>"><?= htmlspecialchars(Release::$STATE_ID_TO_HUMAN[$this->state]) ?></span>
                        <?php if($isReviewer and $this->assignee !== null) { ?>
                        <h6>Assigned to <?= $this->assignee ?>
                          <img src="https://github.com/<?= $this->assignee ?>.png" width="16" onerror="this.src='/res/ghMark.png'; this.onerror=null;"/></h6>
                        <?php } ?>
                    <?php } ?>
                </div>
              <?php } ?>
          </div>
        </div>
          <?php if($this->state === Release::STATE_CHECKED) { ?>
            <div class="release-warning"><h5>
                This is a "Checked" version. Poggit reviewers found no obviously unsafe code, but it has not
                been carefully tested yet. <i>Use at your own risk!</i>
              </h5></div>
          <?php } ?>
        <div class="plugin-top">
          <div class="plugin-top-left">
              <?php $link = Meta::root() . "r/" . $this->artifact . "/" . $this->projectName . ".phar"; ?>
            <div class="download-release">
              <div class="release-download">
                <a href="<?= $link ?>" class="btn btn-primary btn-md text-center" role="button">
                        <span
                            onclick='gaEventRelease(true, <?= json_encode($this->name) ?>, <?= json_encode($this->version) ?>); window.location = <?= json_encode($link, JSON_UNESCAPED_SLASHES) ?>;'>
                                Direct Download</span>
                </a>
                <span class="hover-title btn-warning"
                      onclick="$('#how-to-install').dialog('open')">How to install?</span>
              </div>
              <div class="try-plugin"></div>
              <div class="release-switch">
                  <?php if(count($this->visibleReleases) > 1) { ?>
                    Switch version
                    <select id="releaseVersionHistory"
                            onchange='window.location = getRelativeRootPath() + "p/" + <?= json_encode($this->release["name"]) ?> + "/" + this.value;'>
                      <option style="display:none" disabled selected value>Select a public release</option>
                        <?php foreach($this->visibleReleases as $row) { ?>
                          <option value="<?= htmlspecialchars($row["version"], ENT_QUOTES) ?>"
                              <?= $row["version"] === $this->release["version"] ? "selected" : "" ?>>
                              <?= htmlspecialchars($row["version"]) ?>
                            (<?= date('d M Y', $row["updateTime"]) ?>
                            ) <?= Release::$STATE_ID_TO_HUMAN[$row["state"]] ?>
                          </option>
                        <?php } ?>
                    </select>
                  <?php } ?>
                <div class="release-stats">
                    <?php if(date("M j") === "Apr 1") { ?>
                      <div style="font-weight: 600; font-size: larger;">Price:
                          <?php if($this->releaseStats["totalDl"] != $this->releaseStats["downloads"]) { ?><strike>
                            $<?= $this->releaseStats["totalDl"] / 100 ?></strike><?php } ?>
                        $<?= $this->releaseStats["downloads"] / 100 ?>
                      </div>
                    <?php } else { ?>
                      <div><?= $this->releaseStats["downloads"] ?> Downloads / <?= $this->releaseStats["totalDl"] ?>
                        Total
                      </div>
                    <?php } ?>
                    <?php
                    if($this->releaseStats["count"] > 0) { ?>
                      <div class="release-score">
                        <div class="release-stars">
                            <?php
                            $averageScore = round($this->releaseStats["average"]);
                            for($i = 0; $i < $averageScore; $i++) { ?><img
                              src="<?= Meta::root() ?>res/Full_Star_Yellow.svg"/><?php }
                            for($i = 0; $i < (5 - $averageScore); $i++) { ?><img
                              src="<?= Meta::root() ?>res/Empty_Star.svg" /><?php } ?>
                        </div>
                          <?= $this->releaseStats["count"] ?> Review<?= $this->releaseStats["count"] === 1 ? "" : "s" ?>
                      </div>
                    <?php } ?>
                </div>
              </div>
            </div>

            <div id="how-to-install" style="display: none;" title="How to install plugins?">
              <ol>
                <li autofocus>Click the "Direct download" button. The plugin will be downloaded.</li>
                <li>Copy the downloaded file to your server's <code>plugins</code> folder.</li>
                <li>Run <code>stop</code> on your server, then start it again.</li>
                <!-- TODO more newbie-friendly! -->
              </ol>
            </div>

            <a name="review-anchor"></a>
            <div class="release-build-link"><h6>
                Submitted on <?= htmlspecialchars(date('d M Y', $this->release["created"])) ?> from
                <a href="<?= Meta::root() ?>ci/<?= $this->release["author"] ?>/<?= urlencode($this->release["repo"]) ?>/<?= urlencode($this->projectName) ?>/<?= $this->buildInternal ?>">
                  Dev Build #<?= $this->buildInternal ?></a>,
                    <?= Release::$STATE_ID_TO_HUMAN[$this->state] ?> on
                    <?= htmlspecialchars(date('d M Y', $this->release["stateUpdated"])) ?>
              </h6></div>
              <?php if($this->releaseCompareURL !== "") { ?>
                <div class="release-compare-link"><a target="_blank" href="<?= $this->releaseCompareURL ?>"><h6>
                      Compare with previous
                      approved release v<?= $this->previousApprovedReleaseVersion ?> (build
                      #<?= $this->previousApprovedReleaseInternal ?>)</h6> <?php Mbd::ghLink($this->releaseCompareURL) ?></a>
                </div>
              <?php } ?>
          </div>
        </div>
        <div class="review-wrapper">
          <div class="plugin-table">
            <div class="plugin-prose">
              <div class="plugin-info-description">
                <div class="release-description-header">
                  <div class="release-description">Plugin
                    Description <?php Mbd::displayAnchor("description") ?></div>
                    <?php if($this->state === Release::STATE_CHECKED) { ?>
                      <div id="upvote" title='Approve'
                           class="upvotes vote-button">
                        <img src='<?= Meta::root() ?>res/upvote.png'/>
                          <?= count($this->upVotes) ?? "0" ?>
                      </div>
                      <div id="downvote" title='Reject'
                           class="downvotes vote-button">
                        <img src='<?= Meta::root() ?>res/downvote.png'/>
                          <?= count($this->downVotes) ?? "0" ?>
                      </div>
                    <?php } ?>
                    <?php if(Session::getInstance()->isLoggedIn() && !$isMine) { ?>
                        <?php for($score = 1; $score <= 5; ++$score) { ?>
                        <div class="release-review-intent" data-score="<?= $score ?>">
                          <img src="<?= Meta::root() ?>res/Empty_Star.svg" height="24"/>
                        </div>
                        <?php } ?>
                    <?php } ?>
                </div>
                <div class="plugin-info" id="rdesc" data-desc-type="<?= $this->descType ?>">
                    <?= $this->descType === "txt" ? ("<pre>" . htmlspecialchars($this->description) . "</pre>") : $this->description ?>
                </div>
              </div>
                <?php if($this->changelogData !== null) { ?>
                  <div class="plugin-info-changelog">
                    <div class="form-key">What's new <?php Mbd::displayAnchor("changelog") ?></div>
                    <div class="plugin-info" id="rchlog">
                      <ul>
                          <?php foreach($this->changelogData as $version => $datum) { ?>
                            <li <?= $datum["type"] === "init" ? "data-disabled" : "" ?>>
                              <a href="#changelog-version-<?= $version ?>"><?= $version ?></a></li>
                          <?php } ?>
                      </ul>
                        <?php foreach($this->changelogData as $version => $datum) {
                            $text = $datum["text"];
                            $type = $datum["type"]; ?>
                          <div id="changelog-version-<?= $version ?>">
                              <?= $type === "txt" ? "<pre>$text</pre>" : $text ?>
                          </div>
                        <?php } ?>
                    </div>
                  </div>
                <?php } ?>
                <?php if($writePerm) {
                    $shields = ($this->state >= Config::MIN_PUBLIC_RELEASE_STATE) ? ["state", "api", "dl.total", "dl"] : ["state", "api"] ?>
                  <div class="plugin-info-shields" id="shield-template">
                    <div class="form-key">Shield Markdown / HTML</div>
                    <div class="plugin-info">
                      <!-- @formatter:off -->
                                    <?php foreach ($shields as $shield) { ?>
                                    <div class="release-shield"><img src="<?= Meta::root() ?>shield.<?= $shield ?>/<?= $this->name ?>">
                                        <pre><code>[![](https://poggit.pmmp.io/shield.<?= $shield ?>/<?= $this->name ?>)](https://poggit.pmmp.io/p/<?= $this->name ?>)</code></pre>
                                        <pre><code>&lt;a href="https://poggit.pmmp.io/p/<?= $this->name ?>"&gt;&lt;img src="https://poggit.pmmp.io/shield.<?= $shield ?>/<?= $this->name ?>"&gt;&lt;/a&gt;</code></pre>
                                    </div><hr>
                                    <?php } ?>
                                    <!-- @formatter:on -->
                    </div>
                  </div>
                <?php }
                PluginReview::displayReleaseReviews([$this->release["projectId"]])
                ?>
            </div>
            <div class="plugin-meta-info">
                <?php if(count($this->spoons) > 0) { ?>
                  <div class="plugin-info-wrapper">
                    <div class="form-key">Supported API versions</div>
                    <div class="plugin-info">
                      <div class="info-table" id="supportedSpoonsValue">
                          <?php foreach($this->spoons["since"] as $key => $since) { ?>
                            <div class="api-list">
                              <div class="submit-spoonVersion-from"><?= $since ?></div>
                              <div>-></div>
                              <div class="submit-spoonVersion-to"><?= ($this->spoons["till"][$key]) ?></div>
                            </div>
                          <?php } ?>
                      </div>
                    </div>
                  </div>
                <?php } ?>
                <?php if(count($this->deps) > 0) { ?>
                  <div class="plugin-info-wrapper">
                    <div class="form-key">Dependencies</div>
                    <div class="plugin-info">
                      <div class="info-table" id="dependenciesValue">
                          <?php foreach($this->deps["name"] as $key => $name) {
                              $link = Meta::root() . "p/" . $name . "/" . $this->deps["version"][$key];
                              ?>
                            <div class="submit-dep-wrapper">
                              <div type="text"
                                   class="submit-depName"><?= $name ?> <?= $this->deps["version"][$key] ?>
                              </div>
                              <div class="submit-depRequired">
                                  <?= $this->deps["isHard"][$key] === 1 ? "Required" : "Optional" ?></div>
                              <a href="<?= $link ?>" class="btn btn-primary btn-sm text-center"
                                 role="button">
                                View Plugin
                              </a>
                            </div>
                          <?php } ?>
                      </div>
                    </div>
                  </div>
                <?php } ?>
                <?php if(count($this->assocs) > 0) { ?>
                  <div class="plugin-info-wrapper">
                    <div class="form-key">Associated Plugins</div>
                    <div class="plugin-info">
                      <div class="info-table" id="associatedValue">
                          <?php foreach($this->assocs["name"] as $key => $name) {
                              $link = Meta::root() . "p/" . $name . "/" . $this->assocs["version"][$key];
                              $pharLink = Meta::root() . "r/" . $this->assocs["artifact"][$key] . "/" . $name . ".phar";
                              ?>
                            <div class="submit-assoc-wrapper">
                              <div type="text"
                                   class="submit-assocName <?= $this->assocs["parent"][$key] ? "assoc-parent" : "" ?>"><?= $name ?> <?= $this->assocs["version"][$key] ?>
                              </div>
                              <a href="<?= $pharLink ?>" class="btn btn-primary btn-sm text-center"
                                 role="button">
                                Download
                              </a>
                              <a href="<?= $link ?>" class="btn btn-primary btn-sm text-center"
                                 role="button">
                                View Plugin
                              </a>
                            </div>
                          <?php } ?>
                      </div>
                    </div>
                  </div>
                <?php } ?>
                <?php if(count($this->reqr) > 0) { ?>
                  <div class="plugin-info-wrapper">
                    <div class="form-key">Requirements &amp; Enhancements</div>
                      <?php if(count($this->reqr) > 0) {
                          foreach($this->reqr["type"] as $key => $type) { ?>
                            <div class="plugin-info">
                              <div class="reqs-list">
                                <span
                                    class="font-weight-bold"><?= PluginRequirement::$CONST_TO_DETAILS[$type]["name"] ?></span>
                                <span
                                    class="remark"><?= $this->reqr["isRequire"][$key] === 1 ? "Requirement" : "Enhancement" ?></span>
                                <span><?= htmlspecialchars($this->reqr["details"][$key]) ?></span>
                              </div>
                            </div>
                          <?php }
                      } ?>
                  </div>
                <?php } ?>
                <?php if(count($this->authors) > 0) { ?>
                  <div class="plugin-info-wrapper" id="release-authors">
                    <h5>Producers <?php Mbd::displayAnchor("authors") ?></h5>
                      <?php foreach($this->authors as $level => $authors) { ?>
                        <ul id="release-authors-main">
                          <li class="release-authors-level">
                              <?= Release::$AUTHOR_TO_HUMAN[$level] ?>s:
                            <ul class="plugin-info release-authors-sub">
                                <?php foreach($authors as $uid => $name) { ?>
                                  <li class="release-authors-entry" data-name="<?= $name ?>">
                                    <img src="https://avatars1.githubusercontent.com/u/<?= $uid ?>"
                                         width="16" onerror="this.src='/res/ghMark.png'; this.onerror=null;"/>
                                    @<?= $name ?> <?php Mbd::ghLink("https://github.com/$name") ?>
                                  </li>
                                <?php } ?>
                            </ul>
                          </li>
                        </ul>
                      <?php } ?>
                  </div>
                <?php } ?>
              <div class="plugin-info-wrapper">
                <div class="form-key">License <?php Mbd::displayAnchor("license") ?></div>
                <div class="plugin-info">
                    <?php if($this->license === "none") { ?>
                      <span>No license</span>
                    <?php } elseif($this->license === "custom") { ?>
                      <p>Custom license</p>
                      <span class="action" onclick="$('#license-dialog').dialog('open')">View</span>
                      <div id="license-dialog" title="Custom license">
                        <pre style="white-space: pre-line;" autofocus><?= Mbd::esq($this->licenseText) ?></pre>
                      </div>
                    <?php } else { ?>
                      <a target="_blank"
                         href="https://choosealicense.com/licenses/<?= $this->license ?>"><?= $this->license ?>
                      </a>
                    <?php } ?>
                </div>
              </div>
              <div class="plugin-info-wrapper">
                <div class="form-key">Categories:</div>
                <div class="plugin-info main-category"><?= Release::$CATEGORIES[$this->mainCategory] ?></div>
                  <?php foreach($this->categories as $id => $index) { ?>
                    <div class="plugin-info"><?= Release::$CATEGORIES[$index] ?></div>
                  <?php } ?>
              </div>
                <?php if(strlen($this->keywords) > 0) { ?>
                  <div class="plugin-info-wrapper">
                    <div class="form-key">Keywords</div>
                    <div class="plugin-info">
                      <ul class="plugin-keywords">
                          <?php foreach(explode(" ", $this->keywords) as $keyword) { ?>
                            <li><a href="<?= Meta::root() ?>plugins?term=<?= Mbd::esq($keyword) ?>">
                                    <?= Mbd::esq($keyword) ?></a></li>
                          <?php } ?>
                      </ul>
                    </div>
                  </div>
                <?php } ?>
                <?php if(count($this->permissions) > 0) { ?>
                  <div class="plugin-info-wrapper">
                    <div class="form-key">Permissions</div>
                    <div class="plugin-info">
                      <div id="submit-perms" class="submit-perms-wrapper">
                          <?php foreach($this->permissions as $reason => $perm) { ?>
                            <div><?= htmlspecialchars(Release::$PERMISSIONS[$perm]["name"]) ?></div>
                          <?php } ?>
                      </div>
                    </div>
                  </div>
                <?php } ?>
            </div>
          </div>
        </div>
        <div class="bottom-download-link">
            <?php
            $link = Meta::root() . "r/" . $this->artifact . "/" . $this->projectName . ".phar";
            ?>
          <a href="<?= $link ?>" class="btn btn-primary btn-md text-center" role="button">
                    <span
                        onclick='gaEventRelease(false, <?= json_encode($this->name) ?>, <?= json_encode($this->version) ?>); window.location = <?= json_encode($link, JSON_UNESCAPED_SLASHES) ?>;'>
                        Direct Download</span>
          </a>
        </div>
      </div>
      <?php if(($writePerm &&
              ($this->release["state"] === Release::STATE_DRAFT || $this->release["state"] === Release::STATE_SUBMITTED)) ||
          (Meta::getAdmlv($user) === Meta::ADMLV_ADMIN && $this->release["state"] <= Release::STATE_SUBMITTED)) { ?>
        <div class="delete-release-wrapper">
          <h4>DELETE THIS RELEASE</h4>
          WARNING: If you delete a 'Submitted' release the plugin will start the review process again.
          If you wish to release a new version to replace this submission, and would like to keep this releases
          metadata
          to use in future submission, please EDIT this release and use "Restore to Draft" before submitting a new
          release.
          <span class="btn btn-danger delete-release">Delete This Release</span>
        </div>
      <?php } ?>
      <?php $this->bodyFooter() ?>
      <?php if(!$isMine) { ?>
        <!-- REVIEW DIALOGUE -->
        <div id="review-dialog" title="Review <?= $this->projectName ?>">
          <form>
            <label author="author"><h3><?= $user ?></h3></label>
            <textarea id="review-message" class="review-message" rows="3" cols="20"
                      maxlength="<?= Meta::getAdmlv($user) >= Meta::ADMLV_MODERATOR ? 1024 : 256 ?>"></textarea>
            <div><span id="review-warning"></span></div>
            <!-- Allow form submission with keyboard without duplicating the dialog button -->
            <input type="submit" tabindex="-1" style="position:absolute; top:-1000px;">
          </form>
            <?php if(Meta::getAdmlv($user) < Meta::ADMLV_MODERATOR) { ?>
              <div><p>You can leave one review per plugin release, and delete or update your
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
            if(Meta::getAdmlv($user) >= Meta::ADMLV_MODERATOR) { ?>
              <form action="#">
                <label for="review-criteria">Criteria</label>
                <select name="review-criteria" id="review-criteria">
                    <?php
                    $usedCrits = PluginReview::getUsedCriteria($this->release["releaseId"], PluginReview::getUIDFromName($user));
                    $usedCritsList = array_map(function($usedCrit) {
                        return $usedCrit['criteria'];
                    }, $usedCrits);
                    foreach(PluginReview::$CRITERIA_HUMAN as $key => $criteria) { ?>
                      <option
                          value="<?= $key ?>" <?= in_array($key, $usedCritsList, true) ? "hidden='true'" : "selected" ?>><?= $criteria ?></option>
                    <?php } ?>
                </select>
              </form>
            <?php } ?>
        </div>
      <?php } ?>
      <?php if($session->isLoggedIn() && $this->state === Release::STATE_CHECKED) { ?>
        <!-- VOTING DIALOGUES -->
        <div id="voteup-dialog" title="Voting <?= $this->projectName ?>">
          <form>
            <label plugin="plugin"><h4><?= $this->projectName ?></h4></label>
              <?php if($this->myVote > 0) { ?>
                <label><h6>You have already voted to accept this plugin</h6></label>
              <?php } elseif($this->myVote < 0) { ?>
                <label><h6>You previously voted to reject this plugin</h6></label>
                <label><h6>Click below to change your vote</h6></label>
              <?php } else { ?>
                <label><h6>Click "Approve" to approve this plugin</h6></label>
              <?php } ?>
            <!-- Allow form submission with keyboard without duplicating the dialog button -->
            <input type="submit" tabindex="-1" style="position:absolute; top:-1000px;">
          </form>
        </div>
        <div id="votedown-dialog" title="Voting <?= $this->projectName ?>">
          <form>
            <label plugin="plugin"><h4><?= $this->projectName ?></h4></label>
              <?php if($this->myVote > 0) { ?>
                <label><h6>You previously voted to accept this plugin</h6></label>
                <label><h6>Click below to reject it, and leave a short reason. The reason will only be visible
                    to
                    admins to prevent abuse.</h6></label>
              <?php } elseif($this->myVote < 0) { ?>
                <label><h6>You have already voted to reject this plugin</h6></label>
                <label><h6>Click below to confirm and update the reason. The reason will only be visible to
                    admins to prevent abuse.</h6></label>
              <?php } else { ?>
                <label><h6>Click 'Reject' to reject this plugin, and leave a short reason below. <strong>Only
                      admins can see this message.</strong></h6></label>
                <label><h6>Do not reject plugins just because they are too old; they should just stay there and you
                    should not use them.</strong></h6></label>
              <?php } ?>
            <textarea id="vote-message"
                      maxlength="255" rows="3"
                      cols="20" class="vote-message"><?= $this->myVoteMessage ?? "" ?></textarea>
            <!-- Allow form submission with keyboard without duplicating the dialog button -->
            <input type="submit" tabindex="-1" style="position:absolute; top:-1000px;">
            <label id="vote-error" class="vote-error"></label>
          </form>
        </div>
      <?php } ?>
      <div id="release-description-bad-dialog" title="Failed to paginate plugin description." style="display: none;">
        <p id="release-description-bad-reason"></p>
      </div>
      <div id="dialog-confirm" title="Delete this Release?" style="display: none;">
        <p><span class="ui-icon ui-icon-alert" style="float:left; margin:12px 12px 20px 0;"></span>This Release will
          be permanently deleted and cannot be recovered. Are you sure?</p>
      </div>
      <div id="wait-spinner" class="loading">Loading...</div>
      <?php
      Module::queueJs("jquery.verticalTabs"); // verticalTabs depends on jquery-ui, so include after BasicJs
      Module::queueJs("release.details");
      $this->flushJsList();
      ?>
      </body>
      </html>
        <?php
        OutputManager::endMinifyHtml($minifier);
    }
}
