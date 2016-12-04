/*
 * Copyright 2016 poggit
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
var textarearows = 7;

function initOrg(name, isOrg) {
    var div = $("<div></div>");
    div.addClass("toggle");
    div.html("<p>Loading repos...</p>");
    div.attr("data-name", name);
    div.attr("data-opened", "true");
    var wrapper = toggleFunc(div);
    ghApi((isOrg ? "orgs" : "users") + "/" + name + "/repos", {}, "GET", function(data) {
        var table = $("<table></table>");
        for(var i = 0; i < data.length; i++) {
            var repo = data[i];
            var brief = typeof briefEnabledRepos[repo.id] !== typeof undefined ? briefEnabledRepos[repo.id] : null;
            var tr = $("<tr></tr>");
            var td0 = $("<td></td>");
            td0.text(repo.name.substr(0, 14) + (repo.name.length > 14 ? '...' : ''));
            td0.appendTo(tr);
            var td1 = $("<td id=prj-" + repo.id + "></td>");
            if(brief !== null && brief.projectsCount) {
                td1.append(brief.projectsCount);
            }
            td1.appendTo(tr);
            var td2 = $("<td></td>");
            var button = $("<span id=btn-" + repo.id + "></span>");
            button.text((brief === null || brief.projectsCount === 0) ? "Enable" : "Disable");
            button.addClass("action");
            button.click((function(briefData, repo) {
                return function() {
                    var enableRepoBuilds = $("#enableRepoBuilds");
                    enableRepoBuilds.data("repoId", repo.id);
                    var enable = briefData !== null && briefData.projectsCount === 0;
                    var enableText = enable ? "Enable" : "Disable";
                    enableRepoBuilds.data("target", (enable) ? "true" : "false");
                    if(enable) {
                        loadToggleDetails(enableRepoBuilds, repo);
                        $(".ui-dialog-buttonpane button:contains('Confirm')").button("disable");
                    } else {
                        $(".ui-dialog-buttonpane button:contains('Confirm')").button("enable");
                    }
                    enableRepoBuilds.dialog({
                        title: enableText + " Poggit-CI for " + repo.full_name
                    });
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
            detailLoader.empty();
            var selectFilePara = $("<p></p>");
            selectFilePara.text("After Poggit-CI is enabled for this repo, a manifest file will be created at: ");
            var select = $("<select id='selectManifestFile'>" +
                "<option value='.poggit/.poggit.yml'>.poggit/.poggit.yml</option>" +
                "<option value='.poggit.yml'>.poggit.yml</option>" +
                "</select>");
            select.appendTo(selectFilePara);
            selectFilePara.appendTo(detailLoader);
            var contentPara = $("<div class='manifestarea'>Content of the manifest:<br/></div>");
            var textArea = $("<textarea id='inputManifestContent' rows='" + textarearows + "'></textarea>");
            textArea.text(yaml);
            textArea.appendTo(contentPara);
            contentPara.appendTo(detailLoader);
            $(".ui-dialog-buttonpane button:contains('Confirm')").button("enable");
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
    if(data.enabled === "true" && (selectManifestFile = enableRepoBuilds.find("#selectManifestFile"))) {
        data.manifestFile = selectManifestFile.val();
        data.manifestContent = enableRepoBuilds.find("#inputManifestContent").val();
    }
    ajax("ajax.toggleRepo", {
        data: data,
        method: "POST",
        success: function(data) {
            $("#btn-" + data.repoId).text(data.enabled ? "Disable" : "Enable");
            if(!data.enabled) {
                $("#repo-" + data.repoId).remove();
                briefEnabledRepos[data.repoId]["projectsCount"] = 0;
                $("#prj-" + data.repoId).text(briefEnabledRepos[data.repoId]["projectsCount"]);
            } else {
                briefEnabledRepos[data.repoId]["projectsCount"] = data.projectscount;
                $("#prj-" + data.repoId).text(briefEnabledRepos[data.repoId]["projectsCount"]);
                $(".repopane").prepend(data.panelhtml);
                $("#detailLoader").empty();
            }
            dialog.dialog("close");
            $(".ui-dialog-buttonpane button:contains('Confirm')").button("enable");
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

$(document).ready(function() {
    var inputUser = $("#inputUser");
    var inputRepo = $("#inputRepo");
    var inputProject = $("#inputProject");
    var inputBuild = $("#inputBuild");
    var gotoRecent = $("#gotoRecent");
    var gotoSelf = $("#gotoSelf");
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
        inputUser.focus();
    } else {
        var topOffset = $("a[name=" + window.location.hash.substring(1) + "]").parent().offset().top;
        $("html, body").animate({
            scrollTop: topOffset
        }, 300);
    }

    inputUser.keydown(function() {
        setTimeout(listener, 50)
    });
    inputUser.change(listener);
    inputUser.keyup(function(event) {
        if(event.keyCode == 13) gotoUser.click();
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
        window.location = getRelativeRootPath() + "ci";
    });
    gotoRecent.click(function() {
        window.location = getRelativeRootPath() + "ci/recent";
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
    var modalPos = {my: "center top", at: "center top+50", of: window};
    enableRepoBuilds.dialog({
        autoOpen: false,
        dialogClass: "no-close",
        position: modalPos,
        closeOnEscape: true,
        close: function(event) {
            if(event.originalEvent) $("#detailLoader").empty();
        },
        buttons: [
            {
                id: "confirm",
                text: "Confirm",
                click: function() {
                    $(".ui-dialog-buttonpane button:contains('Confirm')").button("disable");
                    confirmRepoBuilds($(this), enableRepoBuilds);
                }
            }
        ],
        modal: true
    });
});

var lastBuildHistory = 0x7FFFFFFF;

function buildToRow(build) {
    var tr = $("<tr></tr>");
    var type = $("<td></td>");
    type.text(build.classString);
    type.appendTo(tr);
    var internalId = $("<td></td>");
    internalId.text("#" + build.internal);
    internalId.appendTo(tr);
    var buildLink = $("<a></a>");
    var classPfx = {
        1: "dev",
        2: "beta",
        3: "rc",
        4: "pr"
    };
    buildLink.attr("href", getRelativeRootPath() + "ci/" + projectData.owner + "/" + projectData.name + "/" +
        projectData.project + "/" + classPfx[build.class] + ":" + build.internal);
    internalId.wrapInner(buildLink);
    var branch = $("<td></td>");
    branch.text(build.branch);
    branch.appendTo(tr);
    var sha = $("<td></td>");
    var cause = JSON.parse(build.cause);
    if(cause !== null) {
        if(cause.name == "CommitBuildCause") { // TODO abstraction
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

    // var statuses = JSON.parse(build.status);
    var statuses = [];

    if(statuses === null) statuses = [];
    // var statusNames = {
    //     0: "good",
    //     1: "neutral",
    //     2: "lint",
    //     3: "warn",
    //     4: "error"
    // };
    var lintc = 0;
    for(var i = 0; i < statuses.length; i++) {
        // var status = statuses[i];
        // lint.append(document.createTextNode(statusNames[status.status].ucfirst() + ": " + status.name));
        lintc++;
    }
    if(lintc == 0) {
        lint.append("<span class='affirmative''>Affirmative</span>");
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
            start: lastBuildHistory,
            count: 10
        },
        success: function(data) {
            loadMoreLock = false;
            var $table = $("#project-build-history");
            var builds = data.builds;
            for(var i = 0; i < builds.length; i++) {
                var build = builds[i];
                lastBuildHistory = Math.min(lastBuildHistory, build.internal);
                buildToRow(build).appendTo($table);
            }
        }
    });
}
