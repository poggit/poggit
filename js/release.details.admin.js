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

    var select = $("<select class='inlineselect'></select>").change(function() {
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

    $("<div id='release-admin'></div>")
        .append($("<span class='action'>Comment...</span>")
            .click(function() {
                dialog.dialog("option", "title", "Direct comment");
                textArea.val(`Dear @${releaseDetails.project.repo.owner}:
Regarding the release you submitted, named "**${releaseDetails.name}**" (v${releaseDetails.version}), for the project [${releaseDetails.project.name}](https://poggit.pmmp.io/ci/${releaseDetails.project.repo.owner}/${releaseDetails.project.repo.name}/${releaseDetails.project.name}) on ${new Date(releaseDetails.created * 1000).toISOString()}, there are some problems with the values in the submit form.



Please [edit the release](https://poggit.pmmp.io/edit/${releaseDetails.project.repo.owner}/${releaseDetails.project.repo.name}/${releaseDetails.project.name}/${releaseDetails.build.internal}) to resolve these problems, then reply below this commit comment to remind our staff to review it again.

> Note: This comment is created here because this is the last commit when the released build was created.

> via Poggit (@poggit-bot)`);
                onCommentPosted = function() {
                    location.replace(getRelativeRootPath() + `p/${releaseDetails.name}/${releaseDetails.version}`);
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
                    ajax("release.statechange", {
                        data: {
                            relId: releaseDetails.releaseId,
                            state: PoggitConsts.ReleaseState.rejected
                        },
                        method: "POST",
                        success: function() {
                            location.replace(getRelativeRootPath() + `p/${releaseDetails.name}/${releaseDetails.version}`);
                        }
                    });
                };
                dialog.dialog("open");
            }))
        .append(select)
        .insertAfter("#release-admin-marker");
});
