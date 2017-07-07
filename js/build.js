/*
 * Copyright 2016-2017 Poggit
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

var briefEnabledRepos = {};

var currentRepoId;
var maxrows = 30;

var databuilds = [];
var datareleases = [];

var classPfx = {
    1: "dev",
    4: "pr"
};

var humanstates = ["Draft", "Rejected", "Submitted", "Checked", "Voted", "Approved", "Featured"];

function initOrg(name, isOrg) {
    var div = $("<div id='togglewrapper' class='togglewrapper'></div>");
    div.html("<p>Loading repos...</p>");
    div.attr("data-name", name);
    if(!isOrg) div.attr("data-opened", "true");
    var wrapper = toggleFunc(div);
    ghApi((isOrg ? "orgs" : "users") + "/" + name + "/repos", {}, "GET", function(data) {
        var table = $("<table></table>");
        table.css("width", "100%");
        for(var i = 0; i < data.length; i++) {
            var repo = data[i];
            var brief = typeof briefEnabledRepos[repo.id] !== typeof undefined ? briefEnabledRepos[repo.id] : null;
            var tr = $("<tr></tr>");
            var td0 = $("<td></td>");
            var repoNameWrapper = $("<a></a>");
            repoNameWrapper.text(repo.name.substr(0, 14) + (repo.name.length > 14 ? '...' : ''));
            repoNameWrapper.attr("href", getRelativeRootPath() + "ci/" + name + "/" + repo.name);
            repoNameWrapper.appendTo(td0);
            var ghWrapper = $("<a><img class='gh-logo' src='" + getRelativeRootPath() + "res/ghMark.png' width='16'/></a>");
            ghWrapper.attr("target", "_blank");
            ghWrapper.attr("href", "https://github.com/" + name + "/" + repo.name);
            ghWrapper.appendTo(td0);
            td0.appendTo(tr);
            var td1 = $("<td id=prj-" + repo.id + "></td>");
            if(brief !== null && brief.projectsCount) {
                td1.append(brief.projectsCount);
            }
            td1.appendTo(tr);
            var td2 = $("<td></td>");
            var button = $("<div class='toggle toggle-light' id=btn-" + repo.id + "></div>");
            button.css("float", "right");
            button.text((brief === null || brief.projectsCount === 0) ? "Enable" : "Disable");
            button.toggles({
                drag: true, // allow dragging the toggle between positions
                click: true, // allow clicking on the toggle
                text: {
                    on: 'ON', // text for the ON position
                    off: 'OFF' // and off
                },
                animate: 250, // animation time (ms)
                easing: 'swing', // animation transition easing function
                checkbox: null, // the checkbox to toggle (for use in forms)
                clicker: null, // element that can be clicked on to toggle. removes binding from the toggle itself (use nesting)
                //width: 50, // width used if not set in css
                //height: 20, // height if not set in css
                type: 'compact' // if this is set to 'select' then the select style toggle will be used
            });
            button.toggles(!(brief === null || brief.projectsCount === 0));
            button.toggleClass('disabled', true);
            button.click((function(briefData, repo) {
                return function() {
                    var enableRepoBuilds = $("#enableRepoBuilds");
                    enableRepoBuilds.data("repoId", repo.id);
                    var enable = briefData !== null && briefData.projectsCount === 0;
                    var enableText = enable ? "Enable" : "Disable";
                    var modalWidth = 'auto';
                    enableRepoBuilds.data("target", (enable) ? "true" : "false");
                    if(enable) {
                        loadToggleDetails(enableRepoBuilds, repo);
                        $("#confirm").attr("disabled", true);
                    } else {
                        modalWidth = '300px';
                        var detailLoader = enableRepoBuilds.find("#detailLoader");
                        detailLoader.text("Click Confirm to Disable Poggit-CI for " + repo.name);
                        $("#confirm").attr("disabled", false);
                    }
                    var modalPosition = {my: "center top", at: "center top+100", of: window};
                    enableRepoBuilds.dialog({
                        title: enableText + " Poggit-CI",
                        width: modalWidth,
                        height: 'auto',
                        position: modalPosition
                    });
                    $(".ui-dialog-titlebar button:contains('Close')").prop("title", "");
                    enableRepoBuilds.dialog("open");
                }
            })(brief, repo));
            button.appendTo(td2);
            td2.appendTo(tr);
            tr.appendTo(table);
        }
        var $wrapper = $(wrapper);
        $wrapper.empty();
        table.appendTo($wrapper);
    });

    return div;
}

function loadToggleDetails(enableRepoBuilds, repo) {
    var detailLoader = enableRepoBuilds.find("#detailLoader");
    detailLoader.text("Loading details...");
    var buttons = enableRepoBuilds.dialog("option", "buttons");
    var confirmButton;
    for(var i = 0; i < buttons.length; i++) {
        var button = buttons[i];
        if(button.id === "confirm") {
            confirmButton = button;
            break;
        }
    }
    console.assert(typeof confirmButton === "object");

    ajax("build.scanRepoProjects", {
        data: {
            repoId: repo.id
        },
        success: function(data) {
            var yaml = data.yaml;
            var rowcount = (yaml.split(/\r\n|\r|\n/).length < maxrows ? yaml.split(/\r\n|\r|\n/).length : maxrows) - 1;
            var pluginName = $("<div class='pluginname'><h3>" + repo.name + "</h3></div>");
            detailLoader.empty();
            pluginName.appendTo(detailLoader);
            var confirmAddDiv = $("<div class='cbinput'></div>");
            var confirmAdd = $('<input type="checkbox" checked id="manifestEditConfirm">');
            confirmAdd.change(function() {
                document.getElementById("selectManifestFile").disabled = !this.checked;
                document.getElementById("inputManifestContent").disabled = !this.checked;
            });
            confirmAdd.appendTo(confirmAddDiv);
            confirmAddDiv.append("Commit default .poggit.yml to the repo");
            confirmAddDiv.appendTo(detailLoader);
            var selectFilePara = $("<p></p>");
            selectFilePara.text("After Poggit-CI is enabled for this repo, a manifest file will be created at: ");
            var select = $("<select id='selectManifestFile'>" +
                "<option value='.poggit.yml' selected>.poggit.yml</option>" +
                "<option value='.poggit/.poggit.yml'>.poggit/.poggit.yml</option>" +
                "</select>");
            select.appendTo(selectFilePara);
            selectFilePara.appendTo(detailLoader);
            var contentPara = $("<div class='manifestarea'>Content of the manifest:<br/></div>");
            var textArea = $("<textarea id='inputManifestContent' rows='" + rowcount + "'></textarea>");
            textArea.text(yaml);
            textArea.appendTo(contentPara);
            contentPara.appendTo(detailLoader);
            $("#enableRepoBuilds").dialog({
                position: {my: "center top", at: "center top+100", of: window}
            });
            $("#confirm").attr("disabled", false);
        },
        "method": "POST"
    });
}

function confirmRepoBuilds(dialog, enableRepoBuilds) {
    var data = {
        repoId: enableRepoBuilds.data("repoId"),
        enabled: enableRepoBuilds.data("target")
    };
    var selectManifestFile;
    if(data.enabled === "true" && document.getElementById("manifestEditConfirm").checked && (selectManifestFile = enableRepoBuilds.find("#selectManifestFile"))) {
        data.manifestFile = selectManifestFile.val();
        data.manifestContent = enableRepoBuilds.find("#inputManifestContent").val();
    }
    ajax("ajax.toggleRepo", {
        data: data,
        method: "POST",
        success: function(data) {
            if(!data.enabled) {
                $("#repo-" + data.repoId).remove();
                briefEnabledRepos[data.repoId]["projectsCount"] = 0;
                $("#prj-" + data.repoId).text(briefEnabledRepos[data.repoId]["projectsCount"]);
            } else {
                briefEnabledRepos[data.repoId]["projectsCount"] = data.projectscount;
                $("#prj-" + data.repoId).text(briefEnabledRepos[data.repoId]["projectsCount"]);
                $(".ajaxpane").prepend(data.panelhtml);
                $("#detailLoader").empty();
            }
            dialog.dialog("close");
            $("#btn-" + data.repoId).toggles(data.enabled);
            $("#confirm").attr("disabled", false);
        }
    });
}

function startToggleOrgs() {
    var toggleOrgs = $("#toggle-orgs");
    if(toggleOrgs.length === 0) return;
    toggleOrgs.empty();
    initOrg(getLoginName(), false).appendTo(toggleOrgs);
    ghApi("user/orgs", {}, "GET", function(data) {
        for(var i = 0; i < data.length; i++) {
            initOrg(data[i].login, true).appendTo(toggleOrgs);
        }
    });
}

$(function() {
    var inputSearch = $("#inputSearch");
    var inputUser = $("#inputUser");
    var inputRepo = $("#inputRepo");
    var inputProject = $("#inputProject");
    var inputBuild = $("#inputBuild");
    var gotoRecent = $("#gotoRecent");
    var gotoAdmin = $("#gotoAdmin");
    var gotoSelf = $("#gotoSelf");
    var gotoSearch = $("#gotoSearch");
    var gotoUser = $("#gotoUser");
    var gotoRepo = $("#gotoRepo");
    var gotoProject = $("#gotoProject");
    var gotoBuild = $("#gotoBuild");
    var listener = function() {
        var disableUser = !Boolean(inputUser.val().trim());
        var disableRepo = !(Boolean(inputUser.val().trim()) && Boolean(inputRepo.val().trim()));
        var disableProject = !(Boolean(inputUser.val().trim()) && Boolean(inputRepo.val().trim()) && Boolean(inputProject.val().trim()));
        var disableBuild = !(Boolean(inputUser.val().trim()) && Boolean(inputRepo.val().trim()) && Boolean(inputProject.val().trim()) && Boolean(inputBuild.val().trim()));
        if(gotoUser.hasClass("disabled") !== disableUser) gotoUser.toggleClass("disabled");
        if(gotoRepo.hasClass("disabled") !== disableRepo) gotoRepo.toggleClass("disabled");
        if(gotoProject.hasClass("disabled") !== disableProject) gotoProject.toggleClass("disabled");
        if(gotoBuild.hasClass("disabled") !== disableBuild) gotoBuild.toggleClass("disabled");
    };

    if(window.location.hash == "") {
        if(!window.matchMedia('(max-width: 900px)').matches) inputSearch.focus();
    } else {
        var offset = $("a[name=" + window.location.hash.substring(1) + "]").parent().offset();
        if(typeof offset != "undefined") {
            $("html, body").animate({
                scrollTop: offset.top
            }, 300);
        }
    }

    inputUser.keydown(function() {
        setTimeout(listener, 50)
    });
    inputUser.change(listener);
    inputUser.keyup(function(event) {
        if(event.keyCode == 13) gotoUser.click();
    });
    inputSearch.keydown(function() {
        setTimeout(listener, 50)
    });
    inputSearch.change(listener);
    inputSearch.keyup(function(event) {
        if(event.keyCode == 13) gotoSearch.click();
    });
    inputRepo.keydown(function() {
        setTimeout(listener, 50)
    });
    inputRepo.change(listener);
    inputRepo.keyup(function(event) {
        if(event.keyCode == 13) gotoRepo.click();
    });
    inputProject.keydown(function() {
        setTimeout(listener, 50)
    });
    inputProject.change(listener);
    inputProject.keyup(function(event) {
        if(event.keyCode == 13) gotoProject.click();
    });
    inputBuild.keydown(function() {
        setTimeout(listener, 50)
    });
    inputBuild.change(listener);
    inputBuild.keyup(function(event) {
        if(event.keyCode == 13) gotoBuild.click();
    });

    gotoSelf.click(function() {
        window.location = getRelativeRootPath() + "ci/" + getLoginName();
    });
    gotoAdmin.click(function() {
        window.location = getRelativeRootPath() + "ci";
    });
    gotoRecent.click(function() {
        window.location = getRelativeRootPath() + "ci/recent";
    });
    gotoSearch.click(function() {
        if(inputSearch.val() === "") {
            $("#searchresults").empty();
        } else {
            $("#searchresults").text("Loading Search Results...");
            var searchstring = inputSearch.val();
            var data = {
                search: searchstring
            };
            ajax("search.ajax", {
                data: data,
                method: "POST",
                success: function(data) {
                    var searchresults = $("#searchresults");
                    searchresults.empty();
                    searchresults.html(data.html);
                    $("#inputSearch").val("");
                },
                error: function(xhr, status, error) {
                    alert(error);
                }
            });
        }
    });
    gotoUser.click(function() {
        var $this = $(this);
        if($this.hasClass("disabled")) {
            alert("Please fill in the required fields");
        } else {
            window.location = getRelativeRootPath() + "ci/" + inputUser.val();
        }
    });
    gotoRepo.click(function() {
        var $this = $(this);
        if($this.hasClass("disabled")) {
            alert("Please fill in the required fields");
        } else {
            window.location = getRelativeRootPath() + "ci/" + inputUser.val() + "/" + inputRepo.val();
        }
    });
    gotoProject.click(function() {
        var $this = $(this);
        if($this.hasClass("disabled")) {
            alert("Please fill in the required fields");
        } else {
            window.location = getRelativeRootPath() + "ci/" + inputUser.val() + "/" + inputRepo.val() + "/" +
                inputProject.val();
        }
    });
    gotoBuild.click(function() {
        var $this = $(this);
        if($this.hasClass("disabled")) {
            alert("Please fill in the required fields");
        } else {
            window.location = getRelativeRootPath() + "ci/" + inputUser.val() + "/" + inputRepo.val() + "/" +
                inputProject.val() + "/" + $("#inputBuildClass").val() + ":" + inputBuild.val();
        }
    });

    var enableRepoBuilds = $("#enableRepoBuilds");
    startToggleOrgs();
    enableRepoBuilds.dialog({
        autoOpen: false,
        dialogClass: "no-close",
        closeOnEscape: true,
        close: function(event) {
            if(event.originalEvent) $("#detailLoader").empty();
        },
        buttons: [
            {
                id: "confirm",
                text: "Confirm",
                click: function() {
                    $("#confirm").attr("disabled", true);
                    confirmRepoBuilds($(this), enableRepoBuilds);
                }
            }
        ],
        modal: true
    });
});

var lastBuildId = 0x7FFFFFFF;

function buildToRow(build) {
    var tr = $("<tr id='" + classPfx[build.class] + build.internal + "'></tr>");
    var type = $("<td></td>");
    type.text(build.classString);
    type.appendTo(tr);
    var internalId = $("<td></td>");
    internalId.text("#" + build.internal);
    internalId.appendTo(tr);
    var buildLink = $("<a></a>");
    buildLink.attr("href", getRelativeRootPath() + "ci/" + projectData.owner + "/" + projectData.name + "/" +
        (projectData.project === projectData.name ? "~" : projectData.project) + "/" + classPfx[build.class] + ":" + build.internal);
    internalId.wrapInner(buildLink);
    var branch = $("<td></td>");
    branch.text(build.branch);
    branch.appendTo(tr);
    var sha = $("<td></td>");
    var cause = JSON.parse(build.cause);
    if(cause !== null) {
        if(cause.name === "CommitBuildCause") { // TODO abstraction
            sha.text("Commit: " + cause.sha.substring(0, 7));
            if(isLoggedIn()) {
                ajax("proxy.api.gh", {
                    data: {
                        url: "repos/" + build.repoOwner + "/" + build.repoName + "/commits/" + cause.sha
                    },
                    success: function(data) {
                        var a = $("<a></a>");
                        a.attr("href", data.html_url);
                        a.text(data.commit.message.split("\n")[0]);
                        a.prepend("<br/>");
                        a.prepend(cause.sha.substring(0, 7) + " by " + data.commit.author.name + ": ");
                        a.attr("title", data.commit.message);
                        a.appendTo(sha.empty());
                    }
                });
            } else {
                sha.attr("title", "Please login with GitHub to see more details");
            }
        }
    }
    sha.appendTo(tr);
    var date = $("<td></td>");
    date.addClass("time");
    date.attr("data-timestamp", build.creation);
    timeTextFunc.call(date);
    date.appendTo(tr);
    var buildId = $("<td></td>");
    buildId.text("&" + build.buildId.toString(16));
    buildId.appendTo(tr);
    var permLink = $("<a></a>");
    permLink.attr("href", getRelativeRootPath() + "babs/" + build.buildId.toString(16));
    buildId.wrapInner(permLink);
    var dlLink = $("<td></td>");
    if(build.resourceId != 1) {
        var a = $("<a>Direct</a>");
        a.attr("href", getRelativeRootPath() + "r/" + build.resourceId + "/" + build.projectName + ".phar");
        dlLink.append("- ");
        a.appendTo(dlLink);
        dlLink.append("<br/>- ");
        a = $("<a>Custom name</a>");
        a.attr("href", "#");
        a.click(function() {
            promptDownloadResource(build.resourceId, build.projectName + ".phar")
        });
        a.appendTo(dlLink);
    }
    dlLink.appendTo(tr);
    var lint = $("<td></td>");

    var statuses = build.statuses;
    if(statuses == null) statuses = [];

    if(statuses.length == 0) {
        lint.append("<span class='affirmative'>PASSED</span>");
        lint.css("text-align", "center");
    } else {
        lint.append("<span class='affirmative'>" + statuses.length + " Problem" + (statuses.length > 1 ? "s" : "") + "</span>");
        lint.css("text-align", "center");
    }
    lint.appendTo(tr);
    var anchor;
    anchor = $("<a></a>");
    anchor.attr("name", "build-internal-" + build.internal);
    if(window.location.hash == "#" + anchor.attr("name")) {
        window.location.href = window.location.hash;
        setTimeout(function() {
            $("html, body").animate({
                scrollTop: $(tr).offset().top
            });
        }, 100);
    }
    anchor.appendTo(tr);
    anchor = $("<a></a>");
    anchor.attr("name", "build-id-" + build.buildId);
    if(window.location.hash == "#" + anchor.attr("name")) {
        window.location.href = window.location.hash;
    }
    anchor.appendTo(tr);
    return tr;
}

function doBuildHistoryFilter() {
    var filter = $("#buildHistoryBranchFilter").val();
    $("#project-build-history").find(".build-row").each(function() {
        var $this = $(this);
        $this.css("display", (filter === "all" || filter === $this.attr("data-branch")) ? "table-row" : "none");
    });
}

var loadMoreLock = false;

function loadMoreHistory(projectId) {
    if(loadMoreLock) {
        return;
        /* already loading */
    }
    loadMoreLock = true;
    ajax("build.history", {
        data: {
            projectId: projectId,
            start: lastBuildId,
            count: 10
        },
        success: function(data) {
            loadMoreLock = false;
            databuilds = databuilds.concat(data.builds);
            datareleases = datareleases.concat(data.releases);
            var buttonText = getReleaseInfo(databuilds, datareleases);

            var table = $("#project-build-history");
            var select = $("#submit-chooseBuild");
            table.empty();
            select.empty();
            var releaseExists;
            var release;
            for(var i = 0; i < databuilds.length; i++) {
                var state = "No Release";
                var version = "n/a";
                var build = databuilds[i];
                var row = buildToRow(build);
                row.addClass("build-row");
                row.attr("data-branch", build.branch);
                row.appendTo(table);
                lastBuildId = Math.min(lastBuildId, build.buildId);
                if(classPfx[build.class] == "pr")
                    continue;
                for(release in datareleases) {
                    if(datareleases[release]["buildId"] == build.buildId) {
                        var state = humanstates[datareleases[release]["state"]];
                        version = datareleases[release]["version"];
                        releaseExists = true;
                        break;
                    }
                }
                $("<option value='" + build.internal + "'>" + classPfx[build.class] +
                    ": " + build.internal + " - " + version + " - " + state + "</option>").appendTo(select);
            }
            doBuildHistoryFilter();

            var selectedIndex = select.val();
            if(selectedIndex == null || selectedIndex < 0) {
                $('#submit-chooseBuild').find(':nth-child(0)').prop('selected', true);
                selectedIndex = 1;
            }
            var selectedID = (typeof databuilds[databuilds.length - selectedIndex] != 'undefined') ? databuilds[databuilds.length - selectedIndex]["buildId"] : "";
            var selectedstate;
            for(release in datareleases) {
                if(datareleases[release]["buildId"] == selectedID) {
                    selectedstate = humanstates[datareleases[release]["state"]];
                    break;
                }
            }
            var buttonText;
            switch(selectedstate) {
                case undefined:
                    if(releaseExists) {
                        buttonText = "Submit Update";
                    } else {
                        buttonText = "Release plugin";
                    }
                    break;

                case 'Draft':
                    buttonText = "Edit drafted release for this build";
                    break;

                case 'Submitted':
                    buttonText = "Edit release for this build";
                    break;
            }

            $("#submit-buttonText").text(buttonText);
            var releaseData = getReleaseUrl(databuilds, datareleases, selectedIndex);
            var viewURL = releaseData[0];
            var owner = releaseData[1];
            var releaseState = releaseData[2];
            var link;
            if(releaseState > 2 || (releaseState > 1 && getLoginName() !== "") || (releaseState >= 0 && (getLoginName() === owner || getAdminLevel() >= 3))) {
                link = "<span id='view-buttonText' class='action view-buttonText' onclick= \" location.href = '" + viewURL + "';\">" + "View Release</span>";
            } else {
                link = "<span id='view-buttonText' class='action disabled view-buttonText'>No Release</span>";
            }
            $("#view-buttonText").replaceWith(link);
        }
    });
}

function updateSelectedBuild(buildIndex) {
    var submitURL = $("#submitProjectForm");
    var action = submitURL.attr("action");
    submitURL.attr("action", action.substr(0, action.lastIndexOf("\/") + 1) + buildIndex.value.toString());

    var buttonText = getReleaseInfo(databuilds, datareleases);
    $("#submit-buttonText").text(buttonText);

    var releaseData = getReleaseUrl(databuilds, datareleases, buildIndex.value);
    var viewURL = releaseData[0];
    var owner = releaseData[1];
    var releaseState = releaseData[2];
    if(releaseState > 2 || (releaseState > 1 && getLoginName() != "") || (releaseState >= 0 && (getLoginName() == owner || getAdminLevel() >= 3))) {
        var link = "<span id='view-buttonText' class='action view-buttonText' onclick= \" location.href = '" + viewURL + "';\">" + "View Release</span>";
    } else {
        var link = "<span id='view-buttonText' class='action disabled view-buttonText'>No Release</span>";
    }
    $("#view-buttonText").replaceWith(link);
}

function getReleaseUrl(databuilds, releases, internal) {
    var releaseName;
    var releaseVersion;
    var releaseState = -1;
    var buildId;
    var owner;
    for(b in databuilds) {
        if(databuilds[b]["internal"] == internal) {
            buildId = databuilds[b]["buildId"];
            owner = databuilds[b]["repoOwner"];
            break;
        }
    }
    for(r in releases) {
        if(releases[r]["buildId"] == buildId && (releases[r]["state"] > 2 || getLoginName() == owner || getAdminLevel() >= 3)) {
            releaseName = releases[r]["name"];
            releaseVersion = releases[r]["version"];
            releaseState = releases[r]["state"];
            break;
        }
    }
    return (typeof releaseVersion != 'undefined') ? [(getRelativeRootPath() + "p/" + releaseName + "/" + releaseVersion), owner, releaseState] : [releaseVersion, owner, releaseState];
}

function getReleaseInfo(builds, releases) {

    var select = $("#submit-chooseBuild");
    var releaseExists;
    var release;
    for(var i = 0; i < builds.length; i++) {
        var build = builds[i];

        if(classPfx[build.class] == "pr")
            continue;
        for(release in releases) {
            if(releases[release]["buildId"] == build.buildId) {
                releaseExists = true;
                break;
            }
        }
    }

    var selectedIndex = select.val();
    if(selectedIndex === null || selectedIndex < 0) {
        $('#submit-chooseBuild').find(':nth-child(0)').prop('selected', true);
        selectedIndex = 1;
    }
    var selectedID = typeof builds[builds.length - selectedIndex] !== 'undefined' ? builds[builds.length - selectedIndex]["buildId"] : 0;
    var selectedstate;
    for(release in releases) {
        if(releases[release]["buildId"] == selectedID) {
            selectedstate = humanstates[releases[release]["state"]];
            break;
        }
    }
    var buttonText;
    switch(selectedstate) {
        case undefined:
            if(releaseExists) {
                buttonText = "Submit Update";
            } else {
                buttonText = "Release plugin";
            }
            break;

        case 'Draft':
            buttonText = "Edit draft for this build";
            break;

        case 'Submitted':
            buttonText = "Edit release for this build";
            break;
    }
    return buttonText;
}

function testWebhook(owner, name) {
    ajax("ci.webhookTest", {
        data: {
            owner: owner,
            name: name
        },
        success: function(data) {
            console.log(data);
        }
    });
}

var toggleProjectSub = function(projectId, level) {
    var projectSubToggle = $("#project-subscribe");
    projectSubToggle.addClass("disabled");
    projectSubToggle.prop('onclick', null).off('click');
    ajax("ci.project.togglesub", {
        data: {
            projectId: projectId,
            level: level
        },
        success: function() {
            window.location.reload(true);
        }
    });
};
