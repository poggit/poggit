$(function() {
    var adminRejectionDialog = $("#adminRejectionDialog");
    adminRejectionDialog.dialog({
        title: "Reject plugin",
        autoOpen: false,
        position: modalPosition,
        modal: true,
        buttons: {
            Reject: function() {
                // var url = <?= json_encode(
                // )?>;
                ghApi(releaseDetails.rejectPath, {body: $("#adminRejectionTextArea").val()}, "POST", function() {
                    ajax("release.statechange", {
                        data: {
                            relId: releaseDetails.releaseId,
                            state: PoggitConsts.ReleaseState.rejected
                        },
                        method: "POST",
                        success: function() {
                            location.assign(location.href);
                        }
                    });
                });
            }
        }
    });
    $("#admin-reject-dialog-trigger").click(function() {
        adminRejectionDialog.dialog("open");
    });
});
