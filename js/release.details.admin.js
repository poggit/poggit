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
    var onCommentPosted;
    var dialog = $("<div></div>");
    var textArea = $("<textarea cols='80' rows='10'></textarea>").appendTo(dialog);
    dialog.dialog({
        autoOpen: false,
        position: modalPosition,
        modal: true,
        buttons: {
            Post: function() {
                ghApi(releaseDetails.rejectPath, {body: textArea.val()}, "POST", onCommentPosted);
            }
        }
    });

    var select = $("<select class='inline-select'></select>").change(function() {
        ajax("release.statechange", {
            data: {
                relId: releaseDetails.releaseId,
                state: select.val()
            },
            method: "POST",
            success: function() {
                location.replace(getRelativeRootPath() + `p/${releaseDetails.name}/${releaseDetails.version}`);
            }
        });
    });
    for(var stateName in PoggitConsts.ReleaseState) {
        if(!PoggitConsts.ReleaseState.hasOwnProperty(stateName)) continue;
        var value = PoggitConsts.ReleaseState[stateName];
        var option = $("<option></option>").attr("value", value).text(stateName.ucfirst());
        option.prop("selected", releaseDetails.state === value);
        option.appendTo(select);
    }

    const assigneeSelect = $("<select></select>");
    for(const name in PoggitConsts.Staff) {
        if(PoggitConsts.Staff.hasOwnProperty(name) && PoggitConsts.Staff[name] >= PoggitConsts.AdminLevel.REVIEWER) {
            assigneeSelect.append($("<option></option>").attr("value", name).text(name))
        }
    }
    assigneeSelect.val(sessionData.session.loginName.toLowerCase());
    const assignDialog = $("<div></div>").append(assigneeSelect)
        .dialog({
            autoOpen: false,
            position: modalPosition,
            modal: true,
            title: "Assign release",
            buttons: {
                Assign: () => ajax("review.assign", {
                    data: {
                        releaseId: releaseDetails.releaseId,
                        assignee: assigneeSelect.val()
                    },
                    success: () => window.location.reload()
                }),
                Unassign: () => ajax("review.assign", {
                    data: {
                        releaseId: releaseDetails.releaseId,
                        assignee: ""
                    },
                    success: () => window.location.reload()
                })
            }
        });

    $("<div id='release-admin'></div>")
        .append($("<span class='action'>Pending...</span>")
            .click(function() {
                dialog.dialog("option", "title", "Mark as pending");
                textArea.val(`Dear @${releaseDetails.project.repo.owner}:
Regarding the release you submitted, named "**${releaseDetails.name}**" (v${releaseDetails.version}), for the project [${releaseDetails.project.name}](https://poggit.pmmp.io/ci/${releaseDetails.project.repo.owner}/${releaseDetails.project.repo.name}/${releaseDetails.project.name}) on ${new Date(releaseDetails.created * 1000).toISOString()}, there are some problems with the values in the submit form.



Your release has been reset to draft. Please [edit the release](https://poggit.pmmp.io/edit/${releaseDetails.project.repo.owner}/${releaseDetails.project.repo.name}/${releaseDetails.project.name}/${releaseDetails.build.internal}) to resolve these problems, then click "Submit" on the edit page to have the plugin reviewed again.

> Note: This comment is created here because this is the last commit when the released build was created.

> via Poggit (@poggit-bot)`);
                onCommentPosted = function() {
                    changeReleaseState(PoggitConsts.ReleaseState.draft);
                };
                dialog.dialog("open");
            }))
        .append($("<span class='action'>Reject...</span>")
            .click(function() {
                dialog.dialog("option", "title", "Reject plugin");
                textArea.val(`Dear @${releaseDetails.project.repo.owner}:
I regret to inform you that the release you submitted, named "**${releaseDetails.name}**" (v${releaseDetails.version}), for the project [${releaseDetails.project.name}](https://poggit.pmmp.io/ci/${releaseDetails.project.repo.owner}/${releaseDetails.project.repo.name}/${releaseDetails.project.name}) on ${new Date(releaseDetails.created * 1000).toISOString()}, has been rejected.



Please resolve the issues listed above and submit the updated plugin again.

> Note: This comment is created here because this is the last commit when the released build was created.

> via Poggit (@poggit-bot)`);
                onCommentPosted = function() {
                    changeReleaseState(PoggitConsts.ReleaseState.rejected);
                };
                dialog.dialog("open");
            }))
        .append(select)
        .append($("<span class='action'>Assign</span>")
            .click(() => assignDialog.dialog("open")))
        .insertAfter("#release-admin-marker");

    function changeReleaseState(state) {
        ajax("release.statechange", {
            data: {
                relId: releaseDetails.releaseId,
                state: state
            },
            method: "POST",
            success: function() {
                location.replace(getRelativeRootPath() + `p/${releaseDetails.name}/${releaseDetails.version}`);
            }
        });
    }
});
