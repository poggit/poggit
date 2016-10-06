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

$(document).ready(function() {
    var inputUser = $("#inputUser");
    var inputRepo = $("#inputRepo");
    var inputProject = $("#inputProject");
    var inputBuild = $("#inputBuild");
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
        var $this = $(this);
        if($this.hasClass("disabled")) {
            alert("Please fill in the required fields");
        } else {
            window.location = "${path.relativeRoot}build";
        }
    });
    gotoUser.click(function() {
        var $this = $(this);
        if($this.hasClass("disabled")) {
            alert("Please fill in the required fields");
        } else {
            window.location = "${path.relativeRoot}build/" + inputUser.val();
        }
    });
    gotoRepo.click(function() {
        var $this = $(this);
        if($this.hasClass("disabled")) {
            alert("Please fill in the required fields");
        } else {
            window.location = "${path.relativeRoot}build/" + inputUser.val() + "/" + inputRepo.val();
        }
    });
    gotoProject.click(function() {
        var $this = $(this);
        if($this.hasClass("disabled")) {
            alert("Please fill in the required fields");
        } else {
            window.location = "${path.relativeRoot}build/" + inputUser.val() + "/" + inputRepo.val() + "/" +
                inputProject.val();
        }
    });
    gotoBuild.click(function() {
        var $this = $(this);
        if($this.hasClass("disabled")) {
            alert("Please fill in the required fields");
        } else {
            window.location = "${path.relativeRoot}build/" + inputUser.val() + "/" + inputRepo.val() + "/" +
                inputProject.val() + "/" + inputBuild.val();
        }
    });
});

var lastBuildHistory = 0x7FFFFFFF;

function buildToRow(build) {
    var tr = $("<tr></tr>");
    var branch = $("<td></td>");
    branch.text(build.branch);
    branch.appendTo(tr);
    var sha = $("<td></td>");
    sha.text(build.head.substring(0, 7));
    if(isLoggedIn()) {
        ajax("proxy.api.gh", {
            data: {
                url: "repos/" + build.repoOwner + "/" + build.repoName + "/commits/" + build.head
            },
            success: function(data) {
                var a = $("<a></a>");
                a.attr("href", data.html_url);
                a.text(
                    build.head.substring(0, 7) + " by " + data.commit.author.name + ": " +
                    data.commit.message.split("\n")[0]
                );
                a.attr("title", data.commit.message);
                a.appendTo(sha.empty());
            }
        });
    } else {
        sha.attr("title", "Please login with GitHub to see more details");
    }
    sha.appendTo(tr);
    var date = $("<td></td>");
    date.addClass("time");
    date.attr("data-timestamp", build.creation);
    timeTextFunc.call(date);
    date.appendTo(tr);
    var buildId = $("<td></td>");
    buildId.text("&" + build.buildId);
    buildId.appendTo(tr);
    var internalId = $("<td></td>");
    internalId.text("#" + build.internal);
    internalId.appendTo(tr);
    var dlLink = $("<td></td>");
    var a = $("<a>Direct download</a>");
    a.attr("href", "${path.relativeRoot}r/" + build.resourceId + "/" + build.projectName + ".phar?cookie");
    a.appendTo(dlLink);
    dlLink.append("<br>");
    a = $("<a>Custom download name</a>");
    a.attr("href", "#");
    a.click(function() {
        promptDownloadResource(build.resourceId, build.projectName + ".phar")
    });
    a.appendTo(dlLink);
    dlLink.appendTo(tr);
    var type = $("<td></td>");
    type.text(build.classString);
    type.appendTo(tr);
    var lint = $("<td></td>");
    var statuses = JSON.parse(build.status);
    for(var i = 0; i < statuses.length; i++) {
        var status = statuses[i];
        lint.append(status.name + "<br>");
    }
    console.log(build.status);
    lint.appendTo(tr);
    return tr;
}
function loadMoreHistory(projectId) {
    ajax("build.history", {
        data: {
            projectId: projectId,
            start: lastBuildHistory,
            count: 5
        }, success: function(data) {
            console.log(data);
            var $table = $("#project-build-history");
            for(var i = 0; i < data.length; i++) {
                var build = data[i];
                lastBuildHistory = Math.min(lastBuildHistory, build.internal);
                buildToRow(build).appendTo($table);
            }
        }
    });
}
