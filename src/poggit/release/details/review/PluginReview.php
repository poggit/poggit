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
use poggit\module\Module;
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

    public static function displayReleaseReviews(array $projectIds, bool $showRelease = false, int $limit = 50) {
        $types = str_repeat("i", count($projectIds));
        $relIdPhSet = substr(str_repeat(",?", count($projectIds)), 1);
        /** @var PluginReview[] $reviews */
        $reviews = [];
        foreach(Mysql::query("SELECT p.repoId, r.releaseId, r.name, r.version, rr.reviewId,
                rau.name author, UNIX_TIMESTAMP(rr.created) created, rr.type, rr.score, rr.criteria, rr.message
                FROM release_reviews rr INNER JOIN releases r ON rr.releaseId = r.releaseId
                INNER JOIN users rau ON rau.uid = rr.user
                INNER JOIN projects p ON r.projectId = p.projectId
                WHERE p.projectId IN ($relIdPhSet)
                ORDER BY r.releaseId DESC, rr.created DESC LIMIT $limit", $types, ...$projectIds) as $row) {
            $review = new self();
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
        $reviewIds = array_keys($reviews);
        if(count($reviewIds) > 0) {
            $types = str_repeat("i", count($reviewIds));
            $reviewIdPhSet = substr(str_repeat(",?", count($reviewIds)), 1);
            foreach(Mysql::query("SELECT
                rr.reviewId, rrr.user, rrra.name authorName, rrr.message, UNIX_TIMESTAMP(rrr.created) created
                FROM release_reply_reviews rrr INNER JOIN release_reviews rr ON rrr.reviewId = rr.reviewId
                INNER JOIN users rrra ON rrr.user = rrra.uid
                WHERE rr.reviewId IN ($reviewIdPhSet)
                ORDER BY rr.reviewId, rrr.created DESC", $types, ...$reviewIds) as $row) {
                $reply = new PluginReviewReply();
                $reply->reviewId = (int) $row["reviewId"];
                $reply->authorName = $row["authorName"];
                $reply->message = $row["message"];
                $reply->created = (int) $row["created"];
                $reviews[$reply->reviewId]->replies[$reply->authorName] = $reply;
            }
        }
        ?>
        <script>var knownReviews = <?= json_encode($reviews, JSON_UNESCAPED_SLASHES) ?>;</script>
        <?php
        foreach($reviews as $review) {
            if($review instanceof self) self::displayReview($review, $showRelease);
        }
        self::reviewReplyDialog();
    }

    public static function displayReview(PluginReview $review, bool $showRelease = false) {
        $session = Session::getInstance();
        ?>
        <div class="review-outer-wrapper">
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
                    <div class="review-details">
                        <div class="review-authorname"><?= htmlspecialchars($review->authorName) ?></div>
                        <div class="review-version">(v.<?= htmlspecialchars($review->releaseVersion) ?>)</div>
                        <div class="review-date"><?= date("d M", $review->created) ?></div>
                    </div>
                    <?php if(!isset($review->replies[$session->getName()]) and ReviewReplyAjax::mayReplyTo($review->releaseRepoId)) { ?>
                        <div class="review-reply-btn">
                        <span class="action reply-review-dialog-trigger"
                              data-reviewId="<?= json_encode($review->reviewId) ?>">Reply</span>
                        </div>
                    <?php } ?>
                    <?php if(strtolower($review->authorName) === strtolower($session->getName()) || Meta::getAdmlv($session->getName()) >= Meta::ADMLV_MODERATOR) { ?>
                        <div class="action review-delete" criteria="<?= $review->criteria ?? 0 ?>"
                             onclick="deleteReview(this)"
                             value="<?= $review->releaseId ?>">x
                        </div>
                    <?php } ?>
                </div>
                <div class="review-panel-left">
                    <div class="review-score review-info">
                        <?php for ($i = 0; $i < $review->score; $i++) { ?> * <?php } ?>
                    </div>
                </div>
            </div>
            <?php if (strlen($review->message) > 0){ ?>
            <div class="review-panel-right plugin-info">
                <span class="review-textarea"><?= htmlspecialchars($review->message) ?></span>
            </div>
            <?php } ?>
            <div class="review-replies">
                <?php foreach($review->replies as $reply) { ?>
                    <div class="review-reply">
                        <div class="review-header-wrapper">
                            <!-- TODO change these to reply-specific classes -->
                            <div class="review-header">
                                <h6><?= htmlspecialchars($reply->authorName) ?></h6>
                                <div class="review-date"><?= date("d M", $reply->created) ?></div>
                            </div>
                            <?php if(strtolower($reply->authorName) === strtolower($session->getName())) { ?>
                                <div class="edit-reply-btn">
                            <span class="action reply-review-dialog-trigger"
                                  data-reviewId="<?= json_encode($review->reviewId) ?>">Edit</span>
                                </div>
                            <?php } ?>
                        </div>
                        <div class="plugin-info">
                            <span class="review-textarea"><?= htmlspecialchars($reply->message) ?></span>
                        </div>
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
        <?php
        Module::queueJs("release.review");
    }
}
