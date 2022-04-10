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
    const dialog = $("<div></div>");
    const textArea1 = $(`<textarea cols='80' rows='3'>Dear @${releaseDetails.project.repo.owner},</textarea>`).appendTo(dialog);
    const textArea2 = $("<textarea cols='80' rows='5'></textarea>").appendTo(dialog);
    const textArea3 = $("<textarea cols='80' rows='2'></textarea>").appendTo(dialog);

    const dialogSelectState = $("<select></select>")
        .append($(`<option value='keep' selected>Unchanged</option>`))
        .append($("<option value='rejected'>Rejected</option>"))
        .append($("<option value='draft'>Draft</option>"));
    dialog.append($("<div>Change state to: </div>").append(dialogSelectState));

    let serverRules;
    const insertRuleSelect = $("<select></select>").appendTo(dialog);
    $("<button>Add</button>").click(() => {
        let text = textArea2.val();
        const rule = serverRules[insertRuleSelect.val()];
        text += `\n\n${rule.id} &mdash; ${rule.title}:\n> ${rule.content}`;
        citations.push(rule.id);
        textArea2.val(text);
    }).appendTo(dialog);

    ajax("submit.rules.api", {
        success: (rules) => {
            serverRules = rules;
            for(const id in rules){
                if(!rules.hasOwnProperty(id)) continue;
                const rule = rules[id];
                insertRuleSelect.append($("<option></option>").attr("value", rule.id).text(`${rule.id} (${rule.uses}) - ${rule.title}`))
            }
        }
    });

    const citations = [];

    dialog.dialog({
        autoOpen: false,
        position: modalPosition,
        width: window.innerWidth * 0.8,
        modal: true,
        buttons: {
            Post: function() {
                const message = `${textArea1.val()}\n\n${textArea2.val()}\n\n${textArea3.val()}

> This comment is posted here because this is the last commit when the released build was created.`;
                ghApi(releaseDetails.rejectPath, {body: message}, "POST", () => {
                    switch(dialogSelectState.val()){
                        case "rejected":
                            changeState(PoggitConsts.ReleaseState.rejected, message);
                            break;
                        case "draft":
                            changeState(PoggitConsts.ReleaseState.draft, message);
                            break;
                    }
                });
            },
        },
    });

    dialogSelectState.change(() => {
        const newValue = dialogSelectState.val();
        if(newValue === "rejected") {
            textArea1.val(`Dear @${releaseDetails.project.repo.owner},
> I regret to inform you that your plugin "**${releaseDetails.name}**" (v${releaseDetails.version} submitted on ${new Date(releaseDetails.created * 1000).toISOString()}) has been rejected.`);
            textArea3.val("Please resolve these issues and submit the plugin again.")
        } else if(newValue === "draft") {
            textArea1.val(`There are some problems with the plugin submission form for "**${releaseDetails.name}**" (v${releaseDetails.version} submitted on ${new Date(releaseDetails.created * 1000).toISOString()}):`);
            textArea3.val(`Your release has been reset to draft. Please [edit the release](https://poggit.pmmp.io/edit/${releaseDetails.project.repo.owner}/${releaseDetails.project.repo.name}/${releaseDetails.project.name}/${releaseDetails.build.internal}) to resolve these problems, then click "Submit" on the edit page to have the plugin reviewed again.`);
        }
    });

    function changeState(state, message) {
        ajax("release.statechange", {
            data: {relId: releaseDetails.releaseId, state: state, message: message, citations: citations.join(",")},
            method: "POST",
            success: () => location.reload(),
            error: function(error){
                var message = "Unknown error has occurred, please try again later.\nError Code: " + error.status;
                if(error.responseJSON && error.responseJSON.message) message = error.responseJSON.message;
                alert(message);
                location.reload()
            }
        });
    }

    const select = $("<select class='inline-select'></select>").change(() => changeState(select.val()));
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
        .append($("<span class='action'>Message</span>")
            .click(function() {
                dialog.dialog("option", "title", "Message author");
                dialog.dialog("open")
            }))
        .append(select)
        .append($("<span class='action'>Assign</span>")
            .click(() => assignDialog.dialog("open")))
        .insertAfter("#release-admin-marker");
});
