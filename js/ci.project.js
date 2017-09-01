$(function() {
    var knownBuilds = {};
    var currentHistoryConfig = null;
    var currentBuildStatus = null;

    var buildDiv = $("#ci-build-inner");
    var buildPane = $("#ci-build-pane");
    $("#ci-build-close").click(function() {
        showProject(true);
    });
    var isBuildDivDisplayed = false;
    var isNarrow;

    var getDialog = (function() {
        var dialog;
        return function() {
            if(typeof dialog !== "undefined") return dialog;
            dialog = $("<div></div>");
            dialog.dialog({
                autoOpen: false,
                modal: false
            });
            console.log(dialog);
            return dialog;
        };
    })();

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

    var narrowHandlers = {
        showNarrow: function() {
            var dialog = getDialog();
            buildDiv.appendTo(dialog);
            dialog.dialog("open");
        },
        hideNarrow: function() {
            var dialog = getDialog();
            dialog.dialog("close");
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
                    console.log("Dialog");
                    narrowHandlers.hideWide();
                    narrowHandlers.showNarrow();
                } else {
                    console.log("Flex");
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
    })();


    function showBuild(clazz, internal, replace) {
        function getTitle(clazz, internal) {
            return PoggitConsts.BuildClass[clazz] + " Build #" + internal + " @ " + projectData.path[2] + " (" + projectData.path[0] + "/" + projectData.path[1] + ")";
        }

        var newUrl = getRelativeRootPath() + "ci/" + projectData.path.join("/") + "/" + PoggitConsts.BuildClass[clazz].toLowerCase() + ":" + internal;
        currentBuildStatus = {clazz: clazz, internal: internal};
        if(!replace) history.pushState(null, "", newUrl);
        document.title = getTitle(clazz, internal);

        $("#ci-build-header").text(PoggitConsts.BuildClass[clazz] + " Build #" + internal);

        displayBuildDiv();
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
                    knownBuilds[data[i].buildId] = data[i];
                    tail = data[i].internal;
                }
                for(i = 0; i < data.length; ++i) {
                    table.append(getBuildRow(data[i].buildId))
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
                    .click(clickShowBuild)))
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
                (build.lintCount + " problems")).css("margin-bottom", "0"))
            .appendTo(row);

        var commitMessageSpan = $("<span></span>");
        $("<td class='ci-project-history-commit'></td>")
            .append($("<a target='_blank'></a>")
                .attr("href", "https://github.com/" + projectData.path[0] + "/" + projectData.path[1] + "/commit/" + build.sha)
                .append($("<code></code>").text(build.sha.substring(0, 7))))
            .append(commitMessageSpan)
            .appendTo(row);
        ghApi("repositories/" + projectData.project.repoId + "/commits/" + build.sha, {}, "GET", function(data) {
            commitMessageSpan.text(data.commit.message.split("\n")[0]);
            $("<a target='_blank'></a>").attr("href", data.author.html_url)
                .attr("title", data.commit.author.name)
                .append($("<img width='16'/>").attr("src", data.author.avatar_url).css("margin", "5px"))
                .prependTo(commitMessageSpan);
        });

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
            .append($("<a></a>").text((Math.round(build.dlSize / 102.4) / 10).toString() + " KB")
                .attr("href", dlLink)
                .click(function() {
                    return projectData.project.projectId === 210 ||
                        confirm("This " + (projectData.project.projectType === 2 ? "virion" : "plugin") + " has not been reviewed, " +
                            "and it may contain dangerous code like viruses. Do you still want to download this file?");
                }))
            .appendTo(row);

        return build.$row = row;
    }

    handlePathName(false);

    var regex = /^https:\/\/github\.com\/([a-z\d](?:[a-z\d]|-(?=[a-z\d])){0,38})\/([a-z0-9_.-]+)\/tree\/master\/?(.*)/i;
    var ppa = document.getElementById("projectPath");
    var exec = regex.exec(ppa.href);
    ghApi("repos/" + exec[1] + "/" + exec[2], {}, "GET", function(data) {
        ppa.href = "https://github.com/" + data.full_name + "/tree/" + data.default_branch + "/" + exec[3];
    });

    realLoadBuildHistory(getParameterByName("branch", "special:dev"), -1, 10);

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
                console.log("Displaying", builds[i]);
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
