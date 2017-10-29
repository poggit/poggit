$(function() {
    $(".reply-review-dialog-trigger").click(function() {
        replyReviewDialog(this.getAttribute("data-reviewId"));
    });

    var reviewReplyDialog = $("#review-reply-dialog");
    reviewReplyDialog.dialog({
        autoOpen: false,
        modal: true,
        buttons: {
            "Submit Reply": function() {
                postReviewReply(reviewReplyDialog.attr("data-forReview"), reviewReplyDialog.find("#review-reply-dialog-message").val());
            },
            "Delete Reply": function() {
                deleteReviewReply(reviewReplyDialog.attr("data-forReview"));
            }
        }
    });

    function replyReviewDialog(reviewId) {
        reviewReplyDialog.attr("data-forReview", reviewId);
        reviewReplyDialog.find("#review-reply-dialog-author").text(knownReviews[reviewId].authorName);
        reviewReplyDialog.find("#review-reply-dialog-quote").text(knownReviews[reviewId].message);
        if(knownReviews[reviewId].replies[getLoginName().toLowerCase()] !== undefined) {
            reviewReplyDialog.find("#review-reply-dialog-message").val(knownReviews[reviewId].replies[getLoginName().toLowerCase()].message);
        }
        reviewReplyDialog.dialog("open");
    }
});
