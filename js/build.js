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

$(function() {
    const maxRows = 30;

    function initOrg(name, isOrg) {
        var div = $("<div id='toggle-wrapper' class='toggle-wrapper'></div>");
        div.html("<p>Loading repos...</p>");
        div.attr("data-name", name);
        div.attr("data-opened", isOrg ? "false" : "true");
        var wrapper = toggleFunc(div);
        ajax("ci.project.list", {
            data: {
                owner: name
            },
            success: function(data) {
                console.log(name);
                var table = $("<table></table>");
                table.css("width", "100%");
                for(var i in data) {
                    if(!data.hasOwnProperty(i)) continue;
                    var repo = data[i];
                    var tr = $("<tr></tr>");
                    $("<td></td>")
                        .append($("<a></a>")
                            .text(repo.name.substr(0, 14) + (repo.name.length > 14 ? '...' : ''))
                            .attr("href", `${getRelativeRootPath()}ci/${name}/${repo.name}`))
                        .append(generateGhLink(`https://github.com/${name}/${repo.name}`))
                        .appendTo(tr);
                    $(`<td id=prj-${repo.id}></td>`)
                        .text(repo.projectsCount > 0 ? repo.projectsCount : "")
                        .appendTo(tr);
                    var button = $(`<div class='toggle toggle-light' id=btn-${repo.id}></div>`);
                    button.css("float", "right");
                    button.text(repo.projectsCount === 0 ? "Enable" : "Disable");
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
                    button.toggles(!(repo.projectsCount === 0));
                    button.toggleClass('disabled', true);
                    button.click((function(repo) {
                        return function() {
                            var enableRepoBuilds = $("#enableRepoBuilds");
                            enableRepoBuilds.data("repoId", repo.id);
                            var enable = repo.projectsCount === 0;
                            var enableText = enable ? "Enable" : "Disable";
                            var modalWidth = 'auto';
                            enableRepoBuilds.data("target", (enable) ? "true" : "false");
                            if(enable) {
                                loadToggleDetails(enableRepoBuilds, repo);
                                $("#confirm").attr("disabled", true);
                            } else {
                                modalWidth = '300px';
                                var detailLoader = enableRepoBuilds.find("#detailLoader");
                                detailLoader.text(`Click Confirm to Disable Poggit-CI for ${repo.name}.`);
                                $("#confirm").attr("disabled", false);
                            }
                            enableRepoBuilds.dialog({
                                title: enableText + " Poggit-CI",
                                width: modalWidth,
                                height: 'auto',
                                position: modalPosition
                            });
                            $(".ui-dialog-titlebar button:contains('Close')").prop("title", "");
                            enableRepoBuilds.dialog("open");
                        }
                    })(repo));
                    tr.append($("<td></td>").append(button));
                    tr.appendTo(table);
                }
                $(wrapper).empty().append(table);
            }
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
                var rowCount = (yaml.split(/\r\n|\r|\n/).length < maxRows ? yaml.split(/\r\n|\r|\n/).length : maxRows) - 1;
                var pluginName = $(`<div><h3>${repo.name}</h3></div>`);
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
                    // "<option value='.poggit/.poggit.yml'>.poggit/.poggit.yml</option>" +
                    "</select>");
                select.appendTo(selectFilePara);
                selectFilePara.appendTo(detailLoader);
                var contentPara = $("<div class='manifestarea'>Content of the manifest:<br/></div>");
                var textArea = $(`<textarea id='inputManifestContent' rows='${rowCount}'></textarea>`);
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
                    $("#prj-" + data.repoId).text("0");
                } else {
                    $("#prj-" + data.repoId).text(data.projectsCount);
                    $(".toggle-ajax-pane").prepend(data.panelHtml);
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

    if(window.location.hash.length === 0) {
        // if(!window.matchMedia('(max-width: 900px)').matches) inputSearch.focus();
    } else {
        var offset = $("a[name=" + window.location.hash.substring(1) + "]").parent().offset();
        if(typeof offset !== "undefined") {
            $("html, body").animate({
                scrollTop: offset.top
            }, 300);
        }
    }

    if(window.location.pathname === "/ci" && !sessionData.session.isLoggedIn){
        window.history.replaceState(null, "", "/ci/recent");
    }

    inputUser.keydown(function() {
        setTimeout(listener, 50)
    });
    inputUser.change(listener);
    inputUser.keyup(function(event) {
        if(event.keyCode === 13) gotoUser.click();
    });
    inputSearch.keydown(function() {
        setTimeout(listener, 50)
    });
    inputSearch.change(listener);
    inputSearch.keyup(function(event) {
        if(event.keyCode === 13) gotoSearch.click();
    });
    inputRepo.keydown(function() {
        setTimeout(listener, 50)
    });
    inputRepo.change(listener);
    inputRepo.keyup(function(event) {
        if(event.keyCode === 13) gotoRepo.click();
    });
    inputProject.keydown(function() {
        setTimeout(listener, 50)
    });
    inputProject.change(listener);
    inputProject.keyup(function(event) {
        if(event.keyCode === 13) gotoProject.click();
    });
    inputBuild.keydown(function() {
        setTimeout(listener, 50)
    });
    inputBuild.change(listener);
    inputBuild.keyup(function(event) {
        if(event.keyCode === 13) gotoBuild.click();
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
        var searchResults = $("#search-results");
        if(inputSearch.val() === "") {
            searchResults.empty();
            searchResults.attr('hidden', true);
        } else {
            searchResults.text("Loading Search Results...");
            var searchString = inputSearch.val();
            var data = {
                search: searchString
            };
            ajax("search.ajax", {
                data: data,
                method: "POST",
                success: function(data) {
                    searchResults.empty();
                    searchResults.html(data.html);
                    searchResults.attr('hidden', false);
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
            window.location = `${getRelativeRootPath()}ci/${inputUser.val()}`;
        }
    });
    gotoRepo.click(function() {
        var $this = $(this);
        if($this.hasClass("disabled")) {
            alert("Please fill in the required fields");
        } else {
            window.location = `${getRelativeRootPath()}ci/${inputUser.val()}/${inputRepo.val()}`;
        }
    });
    gotoProject.click(function() {
        var $this = $(this);
        if($this.hasClass("disabled")) {
            alert("Please fill in the required fields");
        } else {
            window.location = `${getRelativeRootPath()}ci/${inputUser.val()}/${inputRepo.val()}/${inputProject.val()}`;
        }
    });
    gotoBuild.click(function() {
        var $this = $(this);
        if($this.hasClass("disabled")) {
            alert("Please fill in the required fields");
        } else {
            window.location = `${getRelativeRootPath()}ci/${inputUser.val()}/${inputRepo.val()}/${inputProject.val()}/${$("#inputBuildClass").val()}:${inputBuild.val()}`;
        }
    });

    $("#rbp-dl-direct").click(function(event){
        if(!confirm("This is a development build; it has not been reviewed and may contain dangerous code, including viruses. Do you still want to download this file?")){
            event.preventDefault();
        }
    });
    $("#rbp-dl-as").click(function() {
        var id = this.getAttribute("data-rsr-id");
        var defaultName = this.getAttribute("data-dl-name");
        if(!confirm("This is a development build; it has not been reviewed and may contain dangerous code, including viruses. Do you still want to download this file?")){
            return;
        }
        var name = prompt("Filename to download with:", defaultName);
        if(name !== null) {
            window.location = `${getRelativeRootPath()}r/${id}/${name}`;
        }
    });

    $("#toggle-project-sub").click(function() {
        var projectId = this.getAttribute("data-project-id");
        var level = document.getElementById('select-project-sub').value;
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

    var recentWrapper = $("#recentBuilds");
    if(recentWrapper.find('> div').length > 16) {
        if(getParameterByName("usePages", sessionData.opts.usePages !== false ? "on" : "off") === "on") {
            recentWrapper.paginate({
                perPage: 16,
                scope: $('div') // targets all div elements
            });
        }
    }

    var rbpWrapper = $("#repo-list-build-wrapper");
    if(rbpWrapper.find('> div').length > 12) {
        if(getParameterByName("usePages", sessionData.opts.usePages !== false ? "on" : "off") === "on") {
            rbpWrapper.paginate({
                perPage: 12,
                scope: $('div') // targets all div elements
            });
        }
    }
});
