/*
 * Copyright 2016-2018 Poggit
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

$(function() {
    var knownBuilds = {};
    var currentHistoryConfig = null;
    var currentBuildStatus = null;

    var buildDiv = $("#ci-build-inner");
    var buildPane = $("#ci-build-pane");
    $("#ci-build-close").click(function() {
        showProject(true);
    });
    var buildHeader = $("#ci-build-header");
    var isBuildDivDisplayed = false;
    var isNarrow;
    var isAutoClose = false;
    var buildPageLock = null;

    buildDiv.find(".ci-build-commit-message").hover(commitMessageHoverHandler(true), commitMessageHoverHandler(false));

    var getDialog = (function() {
        var dialog;
        return function(nullable) {
            if(typeof dialog !== "undefined") return dialog;
            if(nullable) return null;
            dialog = $("<div id='build-pane-dialog'></div>");
            dialog.dialog({
                autoOpen: false,
                modal: true,
                position: modalPosition,
                close: function() {
                    if(!isAutoClose) showProject(true);
                },
                open: function(event, ui) {
                    $('.ui-widget-overlay').bind('click', function() {
                        $("#build-pane-dialog").dialog('close');
                    });
                }
            });
            return dialog;
        };
    })();

    function resizeDialog(dialog) {
        var bodyDiv = document.getElementById("body");
        dialog.dialog("option", "width", bodyDiv.clientWidth * 0.8);
        dialog.dialog("option", "height", window.innerHeight * 0.8);
    }

    function displayBuildDiv() {
        isBuildDivDisplayed = true;
        if(isNarrow) narrowHandlers.showNarrow();
        else narrowHandlers.showWide();
    }

    function hideBuildDiv() {
        isBuildDivDisplayed = false;
        if(isNarrow) narrowHandlers.hideNarrow();
        else narrowHandlers.hideWide();
    }

    function setBuildTitle(title) {
        var dialog = getDialog(true);
        if(dialog !== null) dialog.dialog("option", "title", title);
        buildHeader.text(title);
    }

    var narrowHandlers = {
        showNarrow: function() {
            var dialog = getDialog();
            dialog.dialog("option", "title", buildHeader.text());
            buildDiv.appendTo(dialog);
            resizeDialog(dialog);
            dialog.dialog("open");
        },
        hideNarrow: function() {
            var dialog = getDialog();
            isAutoClose = true;
            dialog.dialog("close");
            isAutoClose = false;
        },
        showWide: function() {
            buildDiv.appendTo(buildPane);
            buildPane.css("display", "flex");
        },
        hideWide: function() {
            buildPane.css("display", "none");
        }
    };

    (function() {
        function onNarrowModeChange(narrowMode) {
            isNarrow = narrowMode;
            if(isBuildDivDisplayed) {
                if(narrowMode) {
                    narrowHandlers.hideWide();
                    narrowHandlers.showNarrow();
                } else {
                    narrowHandlers.hideNarrow();
                    narrowHandlers.showWide();
                }
            }
        }

        var narrowModeQuery = window.matchMedia("(max-width: 1000px)");
        onNarrowModeChange(narrowModeQuery.matches);
        narrowModeQuery.addListener(function(event) {
            onNarrowModeChange(event.matches);
        });

        $(window).resize(function() {
            var dialog = getDialog(true);
            if(dialog !== null) resizeDialog(dialog);
        });
    })();

    function showBuild(clazz, internal, replace) {
        function getTitle(clazz, internal) {
            return PoggitConsts.BuildClass[clazz] + " Build #" + internal + " @ " + projectData.path[2] + " (" + projectData.path[0] + "/" + projectData.path[1] + ")";
        }

        var newUrl = getRelativeRootPath() + "ci/" + projectData.path.join("/") + "/" + PoggitConsts.BuildClass[clazz].toLowerCase() + ":" + internal;
        currentBuildStatus = {clazz: clazz, internal: internal};
        if(!replace) history.pushState(null, "", newUrl);
        document.title = getTitle(clazz, internal);

        setBuildTitle(PoggitConsts.BuildClass[clazz] + " Build #" + internal);

        displayBuildDiv();

        var myLock = Math.random();
        buildPageLock = myLock;
        buildDiv.find(".ci-build-loading").css("display", "block");
        buildDiv.find(".ci-build-section-content").css("display", "none");

        var build = null;
        for(var buildId in knownBuilds) {
            var thisBuild = knownBuilds[buildId];
            if(thisBuild.internal === internal && thisBuild.class === clazz && typeof thisBuild.statuses !== "undefined") {
                build = thisBuild;
                break;
            }
        }
        if(build !== null && build.statuses !== undefined) {
            // no need to reset page lock, because it is indeed changed.
            populateBuildPane(build);
        } else {
            ajax("ci.build.request", {
                data: {projectId: projectData.project.projectId, "class": clazz, internal: internal},
                success: function(data) {
                    knownBuilds[data.buildId] = data;
                    if(buildPageLock !== myLock) return;
                    populateBuildPane(data);
                    postProcessBuild(data);
                },
                error: function() {
                    alert(projectData.project.projectName + " " + PoggitConsts.BuildClass[clazz] + "#" + internal + " does not exist!");
                }
            });
        }
    }

    function populateBuildPane(buildInfo) {
        buildDiv.find(".ci-build-loading").css("display", "none");

        buildDiv.find("#ci-build-sha").text(buildInfo.sha);
        buildDiv.find(".ci-build-commit-message").attr("data-sha", buildInfo.sha).empty();
        if(buildInfo.commitMessage !== undefined) {
            populateCommitMessage(buildInfo.sha, buildInfo.commitMessage, buildInfo.authorName, buildInfo.authorLogin);
        }

        var downloadUl = buildDiv.find("#ci-build-download");
        downloadUl.empty();
        //Phar
        var dlLink = getRelativeRootPath() + "r/" + buildInfo.resourceId + "/" + projectData.path[2] + "_" +
            PoggitConsts.BuildClass[buildInfo.class].toLowerCase() + "-" + buildInfo.internal + ".phar";
        downloadUl.append(buildInfo.resourceId === 1 ? "Not available." : $("<p style='margin-bottom: 0;'>"+(projectData.project.projectType === 1 ? "Plugin:" : "Virion:" )+" </p>").append($(
            "<a style='color: #0275d8;'></a>")
            .text(projectData.path[2] + "_" + PoggitConsts.BuildClass[buildInfo.class].toLowerCase() + "-" + buildInfo.internal + ".phar" +
                " ("+(Math.round(buildInfo.dlSize / 102.4) / 10).toString() + " KB)")
            .attr("href", dlLink)
            .click(function() {
                const ok = confirm("This build has not been reviewed, and it may contain dangerous code including viruses." +
                    "Do you still want to download this file?");
                gaEventCi(true, !ok, projectData.project.projectName, buildInfo.resourceId, false);
                return ok;
            })));
        //Source Code
        dlLink = "https://github.com/"+projectData.project.repoOwner+"/"+projectData.project.repoName+"/archive/"+buildInfo.sha+".zip";
        downloadUl.append($("<p style='margin-bottom: 0;'>Source Code: </p>").append($("<a style='color: #0275d8;'></a>")
            .text(projectData.project.repoName+"-"+buildInfo.sha.slice(0, 6)+".zip (via GitHub.com)")
            .attr("href", dlLink)
            .click(function() {
                return confirm("This build has not been reviewed, and it may contain dangerous code including viruses." +
                    "Do you still want to download the source code from GitHub ?");
            })));
        //License
        //dlLink = "https://raw.gothubusercontent.com/"+projectData.project.repoOwner+"/"+projectData.project.repoName+"/"+buildInfo.sha+"/"+projectData.project.projectPath+"LICENSE";

        var virionUl = buildDiv.find("#ci-build-virion");
        virionUl.empty();
        let expandVirion = true;
        for(const virionName in buildInfo.virions) {
            if(!buildInfo.virions.hasOwnProperty(virionName)) continue;
            const virion = buildInfo.virions[virionName];
            virionUl.append($("<li></li>")
                .append($("<a></a>").attr("href", "/babs/" + virion.babs)
                .text(`${virionName} v${virion.version} (${virion.branch}@${virion.sha.substr(0, 7)})`)));
            if(expandVirion){
                buildDiv.find(".ci-build-virion-section").css("display", "block");
                expandVirion = false;
            }
        }

        var lintDiv = buildDiv.find("#ci-build-lint");
        lintDiv.empty();
        if(buildInfo.statuses.length === 0) {
            lintDiv.append("<h6>No problems have been found. Well done!</h6>");
        } else {
            for(var i = 0; i < buildInfo.statuses.length; ++i) {
                var status = buildInfo.statuses[i];
                $("<div class='ci-build-lint-info'></div>").attr("data-class", status.class)
                    .append($("<p class='remark'></p>").text("Severity: " + PoggitConsts.LintLevel[status.level]))
                    .append($("<div class='ci-build-lint-details'></div>").html(status.html))
                    .appendTo(lintDiv);
            }
        }

        buildDiv.find(".ci-build-section-content").css("display", "block");
    }

    function showProject(pushState) {
        currentBuildStatus = null;
        document.title = projectData.path[2] + " (" + projectData.path[0] + "/" + projectData.path[1] + ")";
        if(pushState) history.pushState(null, "", getRelativeRootPath() + "ci/" + projectData.path.join("/"));
        hideBuildDiv();
    }

    window.addEventListener("popstate", function() {
        handlePathName(true);
    });

    function handlePathName(stateChange) {
        var path = location.pathname.substring(getRelativeRootPath().length).split(/\//);
        if(path.length >= 5) {
            var parsedBuild = /^(?:(dev|pr):)?(\d+)$/i.exec(path[4]);
            if(parsedBuild !== null) {
                var clazz = 1;
                if(typeof parsedBuild[1] !== "undefined") clazz = parsedBuild[1] === "pr" ? 4 : 1;
                showBuild(clazz, Number(parsedBuild[2]), true);
                return;
            }
        }
        if(stateChange) showProject(false);
    }

    function realLoadBuildHistory(branch, lessThan, count, pr) {
        var data = {
            projectId: projectData.project.projectId,
            branch: branch,
            lt: lessThan,
            count: count
        };
        if(pr) data.pr = "";
        ajax("build.history.new", {
            data: data,
            success: function(data) {
                var table = $("#ci-project-history-table");
                // table.children("tr.ci-project-history-content-row").remove();
                var i, tail = -1;
                for(i = 0; i < data.length; ++i) {
                    if(typeof knownBuilds[data[i].buildId] === "undefined") {
                        // confirm it is not loaded from build pane
                        knownBuilds[data[i].buildId] = data[i];
                    }
                    tail = data[i].internal;
                }
                for(i = 0; i < data.length; ++i) {
                    table.append(getBuildRow(data[i].buildId));
                }

                currentHistoryConfig = {
                    branch: branch,
                    tail: tail,
                    size: (currentHistoryConfig === null ? 0 : currentHistoryConfig.size) + count,
                    pr: pr
                };

                $(".ci-project-history-locks").removeClass("disabled").prop("disabled", false);
            }
        });
    }

    function loadMoreBuildHistory(count) {
        if(currentHistoryConfig === null) return;
        realLoadBuildHistory(currentHistoryConfig.branch, currentHistoryConfig.tail, count, currentHistoryConfig.pr);
    }

    function getBuildRow(buildId) {
        var build = knownBuilds[buildId];
        if(typeof build.$row !== "undefined") {
            return build.$row;
        }
        var row = $("<tr class='ci-project-history-content-row'></tr>");

        var buttonAction = null;
        var isPreRelease = null;
        var isRelease = null;
        if(projectData.release !== null && buildId === projectData.release.buildId && (projectData.release.state > 3 || (projectData.release.state === 3 && isLoggedIn()) || projectData.writePerm)) {
            isRelease = getRelativeRootPath() + "p/" + projectData.release.name + "/" + projectData.release.version;
        }
        if(projectData.preRelease !== null && buildId === projectData.preRelease.buildId && (projectData.preRelease.state > 3 || (projectData.preRelease.state === 3 && isLoggedIn()) || projectData.writePerm)) {
            isPreRelease = getRelativeRootPath() + "p/" + projectData.preRelease.name + "/" + projectData.preRelease.version;
        }
        if (projectData.writePerm) {
            if(build.class === 1 && projectData.project.projectType === 1 && !projectData.project.private) {
                if(projectData.release === null && projectData.preRelease === null) {
                    buttonAction = "Submit";
                } else if(buildId > (projectData.release ? projectData.release.buildId : 0) && buildId > (projectData.preRelease ? projectData.preRelease.buildId : 0)) {
                    buttonAction = "Update";
                }
            }
            if(buttonAction !== null) {
                var button = $("<button class='btn action'>"+buttonAction+"</button>").click(function() {
                    var action = buttonAction.toLowerCase();
                    if(action === "submit" || action === "update") {
                        var waitSpinner = $('#wait-spinner');
                        waitSpinner.modal();
                        window.location = getRelativeRootPath() + action + "/" + projectData.path.join("/") + "/" + build.internal;
                    }
                });
                $("<td class='ci-project-history-action-cell'></td>")
                    .append(button)
                    .appendTo(row);
            }else{
                var title = "A newer build has already been submitted.";
                if(projectData.project.private) title = "Private plugins cannot be submitted.";
                else if(projectData.project.projectType === 2) title = "Virion builds cannot be submitted.";
                $("<td class='ci-project-history-action-cell' style='text-align: center;'></td>")
                    .append($("<span class='ci-project-history-action-cell-text text-danger hover-title' title='"+title+"'>Unavailable</span>"))
                    .appendTo(row);
            }
        }

        var permalink = getRelativeRootPath() + "babs/" + build.buildId.toString(16);
        var clickShowBuild = function() {
            showBuild(build.class, build.internal, false);
            return false;
        };
        $("<td class='ci-project-history-build-number'></td>")
            .append($("<p></p>").css("margin-bottom", "0")
                .append($("<a></a>")
                    .text(PoggitConsts.BuildClass[build.class] + " #" + build.internal)
                    .attr("href", permalink)
                    .click(clickShowBuild))
                .append(isRelease ? (" <a style='color: red;' href='" + isRelease + "'>[R]</a>") :
                    (isPreRelease ? (" <a style='color: red;' href='" + isPreRelease + "'>[P]</a>") : "")))
            .append($("<p></p>").css("margin-bottom", "0")
                .append($("<a></a>")
                    .text("(&" + build.buildId.toString(16) + ")")
                    .attr("href", permalink)
                    .click(clickShowBuild)))
            .appendTo(row);

        $("<td class='ci-project-history-date'></td>")
            .append($("<span class='time-elapse' data-max-elapse='604800'></span>").attr("data-timestamp", build.date))
            .appendTo(row);

        var worstLevel = PoggitConsts.LintLevel[build.worstLint];
        $("<td class='ci-project-history-lint'></td>")
            .addClass("lint-style-" + worstLevel.toLowerCase().replace(" ", "-"))
            .append($("<p></p>").text(build.lintCount === 0 ? "OK" :
                (build.lintCount + " problem" + (build.lintCount !== 1 ? "s" : ""))).css("margin-bottom", "0"))
            .appendTo(row);

        $("<td class='ci-project-history-commit'></td>").hover(commitMessageHoverHandler(true), commitMessageHoverHandler(false))
            .append($("<a target='_blank'></a>")
                .attr("href", "https://github.com/" + projectData.path[0] + "/" + projectData.path[1] + "/commit/" + build.sha)
                .append($("<code></code>").text(build.sha ? build.sha.substring(0, 7) : "unknown")))
            .append($("<span></span>").addClass("ci-build-commit-message").attr("data-sha", build.sha))
            .appendTo(row);

        var branchCell = $("<td class='ci-project-history-branch'></td>").appendTo(row);
        if(build.class === 4) {
            branchCell.text("#" + build.branch);
            branchCell.wrapInner($("<a target='_blank'></a>")
                .attr("href", "https://github.com/" + projectData.path[0] + "/" + projectData.path[1] + "/pull/" + build.branch));
        } else {
            branchCell.text(build.branch);
            branchCell.wrapInner($("<a target='_blank'></a>")
                .attr("href", "https://github.com/" + projectData.path[0] + "/" + projectData.path[1] + "/tree/" + build.branch + "/" + build.path));
        }

        var dlLink = getRelativeRootPath() + "r/" + build.resourceId + "/" + projectData.path[2] + "_" +
            PoggitConsts.BuildClass[build.class].toLowerCase() + "-" + build.internal + ".phar";
        $("<td class='ci-project-history-dl'></td>")
            .append(build.resourceId === 1 ? "N/A" : $("<a></a>").text((Math.round(build.dlSize / 102.4) / 10).toString() + " KB")
                .attr("href", dlLink)
                .click(function() {
                    const ok = confirm("This " + (projectData.project.projectType === 2 ? "virion" : "plugin") + " has not been reviewed, " +
                            "and it may contain dangerous code including viruses. Do you still want to download this file?");
                    gaEventCi(true, !ok, projectData.project.projectName, build.resourceId, false);
                    return ok;
                }))
            .appendTo(row);

        if(projectData.project.projectType === 2) {
            $("<td class='ci-project-history-virion-version'></td>")
                .text(build.virionVersion)
                .appendTo(row);
        }

        postProcessBuild(build);

        return build.$row = row;
    }

    function postProcessBuild(build) {
        ghApi("repositories/" + projectData.project.repoId + "/commits/" + build.sha, {}, "GET", function(data) {
            build.commitMessage = data.commit ? data.commit.message : "unknown";
            build.authorName = data.commit ? data.commit.author.name : "unknown";
            build.authorLogin = data.commit ? data.author.login : "unknown";
            populateCommitMessage(data.sha, build.commitMessage, build.authorName, build.authorLogin);
        });
    }

    function populateCommitMessage(sha, message, authorName, authorLogin) {
        $("span.ci-build-commit-message[data-sha='" + sha + "']").each(function() {
            var commitMessageSpan = $(this);
            commitMessageSpan.empty();
            commitMessageSpan.text(message.split("\n")[0]);
            $("<span class='ci-build-commit-details'></span>")
                .text(message.split("\n").slice(1).join("\n"))
                .appendTo(commitMessageSpan);
            $("<a target='_blank'></a>").attr("href", "https://github.com/" + authorLogin)
                .attr("title", authorName)
                .append($("<img width='16'/>")
                .attr("onerror", "this.src='/res/ghMark.png'; this.onerror=null;")
                .attr("src", "https://github.com/" + authorLogin + ".png?width=20").css("margin", "5px"))
                .prependTo(commitMessageSpan);
        });
    }

    function commitMessageHoverHandler(isIn) {
        return function() {
            $(this).find(".ci-build-commit-details").css("display", isIn ? "block" : "none");
        };
    }

    handlePathName(false);

    var regex = /^https:\/\/github\.com\/([a-z\d](?:[a-z\d]|-(?=[a-z\d])){0,38})\/([a-z0-9_.-]+)\/tree\/master\/?(.*)/i;
    var ppa = document.getElementById("projectPath");
    var exec = regex.exec(ppa.href);
    ghApi("repos/" + exec[1] + "/" + exec[2], {}, "GET", function(data) {
        ppa.href = "https://github.com/" + data.full_name + "/tree/" + data.default_branch + "/" + exec[3];
    });

    var branch = getParameterByName("branch", "special:dev");
    $("#ci-project-history-branch-select").val(branch);
    realLoadBuildHistory(branch, -1, 10);

    $("#ci-project-history-load-more").click(function() {
        if($(this).hasClass("disabled")) return;
        $(".ci-project-history-locks").addClass("disabled").prop("disabled", true);
        loadMoreBuildHistory(10);
    });

    $("#ci-project-history-branch-select").change(function() {
        function matchesBranch(build, branch) {
            if(branch === "special:dev") return true;
            if(branch === "special:pr") return build.class === 4;
            return branch === build.branch;
        }

        var table = $("#ci-project-history-table");
        table.find(".ci-project-history-content-row").detach();
        var newBranch = this.value;

        var builds = Object.valuesToArray(knownBuilds);
        builds.sort(function(a, b) {
            return b.date - a.date; // descending order
        });

        var buildsDisplayed = 0;
        var minBuildNumber = -1;
        for(var i = 0; i < builds.length; ++i) {
            if(matchesBranch(builds[i], newBranch)) {
                table.append(getBuildRow(builds[i].buildId));
                ++buildsDisplayed;
                if(minBuildNumber === -1 || minBuildNumber > builds[i].internal) {
                    minBuildNumber = builds[i].internal;
                }
            }
        }
        if(buildsDisplayed < 10) {
            $(".ci-project-history-locks").addClass("disabled").prop("disabled", true);
            realLoadBuildHistory(newBranch, minBuildNumber, 10 - buildsDisplayed, newBranch === "special:pr");
        }
    });
});
