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
    $(".reply-review-dialog-trigger").click(function() {
        replyReviewDialog(this.getAttribute("data-reviewId"));
    });

    var reviewReplyDialog = $("#review-reply-dialog");
    reviewReplyDialog.dialog({
        autoOpen: false,
        modal: true,
        position: modalPosition,
        buttons: {
            "Submit Reply": function() {
                postReviewReply(reviewReplyDialog.attr("data-forReview"), reviewReplyDialog.find("#review-reply-dialog-message").val());
            },
            "Delete Reply": function() {
                deleteReviewReply(reviewReplyDialog.attr("data-forReview"));
            }
        },
        open: function(event, ui) {
            $('.ui-widget-overlay').bind('click', function() {
                $("#review-reply-dialog").dialog('close');
            });
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

    $(".edit-review-dialog-trigger").click(function() {
        openEditReviewDialog(this.getAttribute("data-reviewId"), this.getAttribute("data-releaseId"));
    });
    var editReviewDialog = $("#review-edit-dialog");
    editReviewDialog.dialog({
        autoOpen: false,
        modal: true,
        position: modalPosition,
        buttons: {
            "Cancel": function() {
                editReviewDialog.dialog("close");
            },
            "Update Review": function() {
                editReview(editReviewDialog.attr("data-forReview"), editReviewDialog.attr("data-forRelease"),
                    editReviewDialog.attr("data-forAuthor"), editReviewDialog.find("#score").val(),
                    editReviewDialog.find("#review-edit-dialog-message").val());
            }
        },
        open: function(event, ui) {
            $('.ui-widget-overlay').bind('click', function() {
                $("#review-edit-dialog").dialog('close');
            });
        }
    });

    function openEditReviewDialog(reviewId, releaseId) {
        editReviewDialog.attr("data-forReview", reviewId);
        editReviewDialog.attr("data-forRelease", releaseId);
        editReviewDialog.attr("data-forAuthor", knownReviews[reviewId].authorName)
        editReviewDialog.find("#score").val(knownReviews[reviewId].score === 0 ? 1 : knownReviews[reviewId].score);
        editReviewDialog.find("#review-edit-dialog-message").val(knownReviews[reviewId].message);
        editReviewDialog.dialog("open");
    }
});
