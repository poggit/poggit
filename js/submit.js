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

function addRowToListInfoTable(tableId, baseId) {
    var clone = $("#" + tableId).clone();
    clone.css("display", "table-row");
    clone.removeAttr("id");
    clone.appendTo($("#" + baseId));
    return clone;
}

function deleteRowFromListInfoTable(span) {
    $(span).parents("tr").remove();
}

function guessReadme(possibleDirs, repoId, repoName) {
    for(var i = 0; i < possibleDirs.length; i++) {
        var url = "repositories/" + repoId + "/contents" + possibleDirs[i];
        ghApi(url, {}, "GET", function(data) {
            for(var j = 0; j < data.length; j++) {
                if(data[j].type == "file" && (data[j].name == "README" || data[j].name == "README.md" || data[j].name == "README.txt")) {
                    var button = $("<span class='action'></span>");
                    button.text("Import description from " + repoName + "/" + data[j].path);
                    button.click((function(datum) {
                        return function() {
                            $.get(datum.download_url, {}, function(data) {
                                $("#submit-pluginDescTextArea").val(data);
                                $("#submit-pluginDescTypeSelect").val("md");
                            })
                        };
                    })(data[j]));
                    button.appendTo($("#possibleDescriptionImports"));
                }
            }
        });
    }
}

function loadDefaultDesc() {
    $.ajax(getRelativeRootPath() + "r/" + pluginSubmitData.lastRelease.description + ".md", {
        dataType: "text",
        headers: {
            Accept: "text/plain"
        },
        success: function(data, status, xhr) {
            document.getElementById("submit-pluginDescTextArea").value = data;
            console.log(xhr.responseURL); // TODO fix this for #submit-pluginDescTypeSelect
        }
    });
}

function setupLicense() {
    (function(licenseSelect, viewLicense, customLicense) {
        viewLicense.click(function() {
            var $this = $(this);
            if($this.hasClass("disabled")) {
                return;
            }
            var dialog = $("#previewLicenseDetailsDialog");
            var aname = dialog.find("#previewLicenseName");
            var pdesc = dialog.find("#previewLicenseDesc");
            var preBody = dialog.find("#previewLicenseBody");
            dialog.dialog("open");
            ghApi("licenses/" + licenseSelect.val(), {}, "GET", function(data) {
                aname.attr("href", data.html_url);
                aname.text(data.name);
                pdesc.text(data.description);
                preBody.text(data.body);
            }, undefined, "Accept: application/vnd.github.drax-preview+json");
        });
        licenseSelect.change(function() {
            customLicense.css("display", this.value == "custom" ? "block" : "none");
            var url = $(this).find(":selected").attr("data-url");
            if(typeof url == "string" && url.length > 0) {
                viewLicense.removeClass("disabled");
            } else {
                viewLicense.addClass("disabled");
            }
        });
        ghApi("licenses", {}, "GET", function(data) {
            data.sort(function(a, b) {
                if(a.featured && !b.featured) {
                    return -1;
                }
                if(!a.featured && b.featured) {
                    return 1;
                }
                return a.key.localeCompare(b.key);
            });
            for(var i = 0; i < data.length; i++) {
                var option = $("<option></option>");
                option.attr("value", data[i].key);
                option.attr("data-url", data[i].url);
                option.text(data[i].name);
                option.appendTo(licenseSelect);
            }
        }, undefined, "Accept: application/vnd.github.drax-preview+json");
    })($("#submit-chooseLicense"), $("#viewLicenseDetails"), $("#submit-customLicense"));
}

function searchDep(tr) {
    var name = tr.find(".submit-depName");
    var version = tr.find(".submit-depVersion");
    ajax("api", {
        data: JSON.stringify({
            request: "releases.get",
            name: name.val(),
            version: version.val()
        }),
        success: function(data) {
            var span = tr.find(".submit-depRelId");
            span.attr("data-relId", data.releaseId);
            span.attr("data-projId", data.projectId);
            span.text(data.name + " v" + data.version)
        }
    });
}

function checkPluginName() {
    var pluginName = $("#submit-pluginName").val();
    var data = {pluginName: pluginName};
    if(pluginSubmitData.lastRelease !== null) data.except = pluginSubmitData.lastRelease.releaseId;
    ajax("ajax.relsubvalidate", {
        data: data,
        method: "POST",
        success: function(data) {
            // TODO better validation
            var after = $("#submit-afterPluginName");
            after.text(data.message);
            after.css("color", data.ok ? "green" : "red");
        }
    });
}

$(document).ready(function() {
    var possible = [""];
    if(pluginSubmitData.projectDetails.path.length > 0) possible.push(pluginSubmitData.projectDetails.path);
    guessReadme(possible, pluginSubmitData.projectDetails.repoId, pluginSubmitData.repo);

    if(pluginSubmitData.lastRelease !== null) loadDefaultDesc();

    setupLicense();

    addRowToListInfoTable("baseSpoonForm", "supportedSpoonsValue").find(".deleteSpoonRow").parent("td").remove();

    $("#previewLicenseDetailsDialog").dialog({
        autoOpen: false,
        width: 600,
        clickOut: true,
        responsive: true,
        height: window.innerHeight * 0.8,
        position: {my: "center top", at: "center top+50", of: window}
    });
});
