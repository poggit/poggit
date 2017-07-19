<?php

/*
 * Poggit
 *
 * Copyright (C) 2016-2017 Poggit
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

namespace poggit\release\details\review;

use poggit\account\Session;
use poggit\Mbd;
use poggit\Meta;
use poggit\utils\internet\Mysql;

// WARNING: Refactoring values in this class requires editing references from JavaScript too.
// Fields in this class are directly exported to JavaScript.
class PluginReview {
    const DEFAULT_CRITERIA = 0;
    public static $REVIEW_TYPE = [
        1 => "Official",
        2 => "User",
        3 => "Robot"
    ];
    public static $CRITERIA_HUMAN = [
        0 => "General",
        1 => "Code",
        2 => "Performance",
        3 => "Usefulness",
        4 => "Concept",
    ];
    public $releaseRepoId;
    public $releaseId;
    public $releaseName;
    public $releaseVersion;
    public $reviewId;
    public $authorName;
    public $created;
    public $type;
    public $score;
    public $criteria;
    public $message;

    /** @var PluginReviewReply[] */
    public $replies = [];

    /**
     * @param int $projectId
     * @return array
     */
    public static function getScores(int $projectId): array {
        $scores = Mysql::query("SELECT SUM(rev.score) AS score, COUNT(*) AS scorecount FROM release_reviews rev
        INNER JOIN releases rel ON rel.releaseId = rev.releaseId
        INNER JOIN projects p ON p.projectId = rel.projectId
        INNER JOIN repos r ON r.repoId = p.repoId
        WHERE rel.projectId = ? AND rel.state > 1 AND rev.user <> r.accessWith", "i", $projectId);

        $totaldl = Mysql::query("SELECT SUM(res.dlCount) AS totaldl FROM resources res
		INNER JOIN releases rel ON rel.projectId = ?
        WHERE res.resourceId = rel.artifact", "i", $projectId);
        return ["total" => $scores[0]["score"] ?? 0, "average" => round(($scores[0]["score"] ?? 0) / ((isset($scores[0]["scorecount"]) && $scores[0]["scorecount"] > 0) ? $scores[0]["scorecount"] : 1), 1), "count" => $scores[0]["scorecount"] ?? 0, "totaldl" => $totaldl[0]["totaldl"] ?? 0];
    }

    public static function getNameFromUID(int $uid): string {
        $username = Mysql::query("SELECT name FROM users WHERE uid = ?", "i", $uid);
        return count($username) > 0 ? $username[0]["name"] : "Unknown";
    }

    public static function getUsedCriteria(int $relId, int $uid): array {
        $usedCategories = Mysql::query("SELECT * FROM release_reviews WHERE (releaseId = ? AND user = ?)", "ii", $relId, $uid);
        return $usedCategories;
    }

    public static function getUIDFromName(string $name): int {
        $uid = Mysql::query("SELECT uid FROM users WHERE name = ?", "s", $name);
        return count($uid) > 0 ? $uid[0]["uid"] : 0;
    }

    public static function displayReleaseReviews(array $relIds, bool $showRelease = false) {
        $types = str_repeat("i", count($relIds));
        $relIdPhSet = substr(str_repeat(",?", count($relIds)), 1);
        /** @var PluginReview[] $reviews */
        $reviews = [];
        foreach(Mysql::query("SELECT p.repoId, r.releaseId, r.name, r.version, rr.reviewId,
                rau.name author, UNIX_TIMESTAMP(rr.created) created, rr.type, rr.score, rr.criteria, rr.message
                FROM release_reviews rr INNER JOIN releases r ON rr.releaseId = r.releaseId
                INNER JOIN users rau ON rau.uid = rr.user
                INNER JOIN projects p ON r.projectId = p.projectId
                WHERE rr.releaseId IN ($relIdPhSet)
                ORDER BY rr.created DESC LIMIT 50", $types, ...$relIds) as $row) {
            $review = new PluginReview();
            $review->releaseRepoId = (int) $row["repoId"];
            $review->releaseId = (int) $row["releaseId"];
            $review->releaseName = $row["name"];
            $review->releaseVersion = $row["version"];
            $review->reviewId = (int) $row["reviewId"];
            $review->authorName = $row["author"];
            $review->created = (int) $row["created"];
            $review->type = (int) $row["type"];
            $review->score = (int) $row["score"];
            $review->criteria = (int) $row["criteria"];
            $review->message = $row["message"];
            $reviews[$review->reviewId] = $review;
        }
        foreach(Mysql::query("SELECT
                rr.reviewId, rrr.user, rrra.name authorName, rrr.message, UNIX_TIMESTAMP(rrr.created) created
                FROM release_reply_reviews rrr INNER JOIN release_reviews rr ON rrr.reviewId = rr.reviewId
                INNER JOIN users rrra ON rrr.user = rrra.uid
                WHERE rr.releaseId IN ($relIdPhSet)
                ORDER BY rr.reviewId, rrr.created DESC", $types, ...$relIds) as $row) {
            $reply = new PluginReviewReply();
            $reply->reviewId = (int) $row["reviewId"];
            $reply->authorName = $row["authorName"];
            $reply->message = $row["message"];
            $reply->created = (int) $row["created"];
            $reviews[$reply->reviewId]->replies[$reply->authorName] = $reply;
        }
        foreach($reviews as $review) {
            self::displayReview($review, $showRelease);
        }
        self::reviewReplyDialog();
    }

    public static function displayReview(PluginReview $review, bool $showRelease = false) {
        $session = Session::getInstance();
        ?>
        <script>knownReviews[<?=json_Encode($review->reviewId)?>] = <?=json_encode($review, JSON_UNESCAPED_SLASHES)?>;</script>
        <div class="review-outer-wrapper-<?= Meta::getAdmlv($review->authorName) ?? 0 ?>">
            <div class="review-author review-info-wrapper">
                <?php if($showRelease) { ?>
                    <div>
                        <h5>
                            <a href="<?= Meta::root() . "p/" . urlencode($review->releaseName) . "/" . urlencode($review->releaseVersion) ?>">
                                <?= htmlspecialchars($review->releaseName) ?>
                            </a>
                        </h5>
                    </div>
                <?php } ?>
                <div id="reviewer" value="<?= Mbd::esq($review->authorName) ?>" class="review-header">
                    <div class="review-details"><h6><?= htmlspecialchars($review->authorName) ?></h6>
                        : <?= date("d M", $review->created) ?></div>
                    <?php if(strtolower($review->authorName) === strtolower($session->getName()) || Meta::getAdmlv($session->getName()) >= Meta::ADMLV_MODERATOR) { ?>
                        <div class="action review-delete" criteria="<?= $review->criteria ?? 0 ?>"
                             onclick="deleteReview(this)"
                             value="<?= $review->releaseId ?>">x
                        </div>
                    <?php } ?>
                </div>
                <div class="review-panel-left">
                    <div class="review-score review-info"><?= $review->score ?>/5</div>
                    <div class="review-type review-info"><?= PluginReview::$REVIEW_TYPE[$review->type] ?></div>
                </div>
            </div>
            <div class="review-panel-right plugin-info">
                <span class="review-textarea"><?= htmlspecialchars($review->message) ?></span>
            </div>

            <div class="review-replies">
                <?php foreach($review->replies as $reply) { ?>
                    <div class="review-reply">
                        <div class="review-header-wrapper">
                            <!-- TODO change these to reply-specific classes -->
                            <div class="review-header">
                                <h6><?= htmlspecialchars($reply->authorName) ?></h6>:
                                <?= date("d M", $reply->created) ?>
                            </div>
                            <?php if(strtolower($reply->authorName) === strtolower($session->getName())) { ?>
                                <div class="edit-reply-btn">
                            <span class="action" onclick="replyReviewDialog($(this).attr('data-reviewId'))"
                                  data-reviewId="<?= json_encode($review->reviewId) ?>">Edit reply</span>
                                </div>
                            <?php } ?>
                        </div>
                        <div class="plugin-info">
                            <span class="review-textarea"><?= htmlspecialchars($reply->message) ?></span>
                        </div>
                    </div>
                <?php } ?>
                <?php if(!isset($review->replies[$session->getName()]) and ReviewReplyAjax::mayReplyTo($review->releaseRepoId)) { ?>
                    <div class="review-reply-btn">
                        <span class="action" onclick="replyReviewDialog($(this).attr('data-reviewId'))"
                              data-reviewId="<?= json_encode($review->reviewId) ?>">Reply</span>
                    </div>
                <?php } ?>
            </div>
        </div>
        <?php
    }

    public static function reviewReplyDialog() {
        ?>
        <div id="review-reply-dialog" data-forReview="0" title="Reply to Review">
            <p>Reply to review by <span id="review-reply-dialog-author"></span>:</p>
            <blockquote id="review-reply-dialog-quote"></blockquote>
            <textarea id="review-reply-dialog-message"></textarea>
        </div>
        <script>
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
        </script>
        <?php
    }
}
