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

namespace poggit\module\releases\review;

use poggit\module\Module;
use poggit\utils\internet\MysqlUtils;

class OfficialReviewModule extends Module {
    
    private $releaseId;
    private $user;
    private $criteria;
    private $type;
    private $cat;
    private $score;
    private $message;
    
    public function storeReview($releaseId, $user, $criteria, $type, $cat, $score, $message): int {

        $reviewId = MysqlUtils::query("INSERT INTO release_reviews (releaseId, user, criteria, type, cat, score, message)"
                . " VALUES (?, ?, ?, ?, ?, ?, ?)", "iiiiiis", $releaseId, $user, $criteria, $type, $cat, $score, $message);
        return $reviewId;
    }
    
    public function getName(): string {
        return "admin.pluginReview";
    }
    private static function getNameFromUID(int $uid): string {
        $username = MysqlUtils::query("SELECT name FROM users WHERE uid = ?", "i", $uid);
        return count($username) > 0 ? $username[0]["name"] : "";
    }

    public function output() {
        // TODO: Implement output() method.
    }
    
    public static function reviewPanel(int $relId) {
        $reviews = MysqlUtils::query("SELECT * FROM release_reviews WHERE releaseId = ?", "i", $relId ?? 0);
        foreach ($reviews as $review) {?>
            <div class="review-outer-wrapper">
                    <div class="review-author review-info-wrapper"><h3><?= self::getNameFromUID($review["user"]) ?? "Unknown" ?></h3>
                        <div class="review-panel-left">
                            <div class="review-score review-info">Stars: <?= $review["score"] ?></div>
                            <div class="review-type review-info">Type: <?= $review["type"] ?></div>
                            <div class="review-cat review-info">Category: <?= $review["cat"] ?></div>
                            <div class="review-criteria review-info">Criteria: <?= $review["criteria"] ?></div>
                        </div>
                    </div>
                    <div class="review-panel-right plugin-info">
                        <span class="review-message"><?= $review["message"] ?></span>
                    </div>
            </div>
<?php
    } }
}
