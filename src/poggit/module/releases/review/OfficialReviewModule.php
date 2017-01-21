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
use poggit\utils\SessionUtils;
use poggit\Poggit;
use poggit\release\PluginRelease;

class OfficialReviewModule extends Module {
    
    public static function storeReview($releaseId, $user, $criteria, $type, $cat, $score, $message): int {

        $reviewId = MysqlUtils::query("INSERT INTO release_reviews (releaseId, user, criteria, type, cat, score, message)"
                . " VALUES (?, ?, ?, ?, ?, ?, ?)", "iiiiiis", $releaseId, $user, $criteria, $type, $cat, $score, $message);
        return $reviewId;
    }
    
    public static function deleteReview(): int {
        $user = SessionUtils::getInstance()->getLogin("Name") ?? "";
        if (Poggit::getAdminLevel($user) > 3 || $user == $username) {
        $reviewId = MysqlUtils::query("DELETE FROM release_reviews WHERE (releaseId, user)"
                    . " VALUES (?, ?)", "ii", $releaseId, $user);           
        }
        return $reviewId;
    }
 
    public static function getNameFromUID(int $uid): string {
        $username = MysqlUtils::query("SELECT name FROM users WHERE uid = ?", "i", $uid);
        return count($username) > 0 ? $username[0]["name"] : "Unknown";
    }
    
    public static function getUsedCriteria(int $relId, int $uid): array {
        $usedCategories = MysqlUtils::query("SELECT * FROM release_reviews WHERE (releaseId = ? AND user = ?)", "ii", $relId, $uid);
        return $usedCategories;
    }
    
    public static function getUIDFromName(string $name): int {
        $uid = MysqlUtils::query("SELECT uid FROM users WHERE name = ?", "i", $name);
        return count($uid) > 0 ? $uid[0]["uid"] : 0;
    }

    public function output() {
        // TODO: Implement output() method.
    }
    
    public static function reviewPanel(int $relId) {
        $user = SessionUtils::getInstance()->getLogin()["name"] ?? "";
        $reviews = MysqlUtils::query("SELECT * FROM release_reviews WHERE releaseId = ?", "i", $relId ?? 0);

            foreach ($reviews as $review) { ?>
            <div class="review-outer-wrapper">
                    <div class="review-author review-info-wrapper">
                            <div id ="reviewer" value="<?= $review["user"] ?>" class="review-header"><h3><?= self::getNameFromUID($review["user"]) ?></h3>
                            <?php if (self::getNameFromUID($review["user"]) == $user || Poggit::getAdminLevel($user) > 3) { ?>
                                <div class="action review-delete" onclick="deleteReview(this)">x</div>
                            <?php } ?>
                            </div>
                    <div class="review-panel-left">
                            <div class="review-score review-info"><?= $review["score"] ?>/5</div>
                            <div class="review-type review-info"><?= PluginRelease::$REVIEW_TYPE[$review["type"]] ?></div>
<!--                        <div class="review-cat review-info">Category: <?= $review["cat"] ?></div>-->
                            <div hidden="true" id="criteria" class="review-criteria review-info" value="<?= $review["criteria"] ?>"><?= PluginRelease::$CRITERIA_HUMAN[$review["criteria"]]?></div>
                    </div>
                    </div>
                    <div class="review-panel-right plugin-info">
                        <span class="review-message"><?= $review["message"] ?></span>
                    </div>
            </div>
            <?php
        }
    }
     
    public function getName(): string {
        return "admin.pluginReview";
    }
}