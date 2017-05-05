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

namespace poggit\release\review;

use poggit\Poggit;
use poggit\release\PluginRelease;
use poggit\utils\internet\MysqlUtils;

class ReviewUtils {
    public static function getNameFromUID(int $uid): string {
        $username = MysqlUtils::query("SELECT name FROM users WHERE uid = ?", "i", $uid);
        return count($username) > 0 ? $username[0]["name"] : "Unknown";
    }

    public static function getUsedCriteria(int $relId, int $uid): array {
        $usedCategories = MysqlUtils::query("SELECT * FROM release_reviews WHERE (releaseId = ? AND user = ?)", "ii", $relId, $uid);
        return $usedCategories;
    }

    public static function getUIDFromName(string $name): int {
        $uid = MysqlUtils::query("SELECT uid FROM users WHERE name = ?", "s", $name);
        return count($uid) > 0 ? $uid[0]["uid"] : 0;
    }

    public static function reviewPanel($relIds, string $user, bool $showRelease = false) {
        $reviews = MysqlUtils::query("SELECT u.name as author, rev.releaseId, rel.version, score, message, type, UNIX_TIMESTAMP(rev.created) AS created, cat, criteria, rel.name AS relname
                        FROM release_reviews rev
                        INNER JOIN releases rel ON rel.releaseId = rev.releaseId
                        INNER JOIN users u ON u.uid = user ORDER BY rev.created DESC LIMIT 50");
        foreach($reviews as $review) {
            if(in_array($review["releaseId"], $relIds)) {
                $releaseName = $review["relname"];
                ?>
                <div
                        class="review-outer-wrapper-<?= Poggit::getAdmlv($review["author"]) ?? "0" ?>">
                    <div class="review-author review-info-wrapper">
                        <div><h5>
                                <a href="<?= Poggit::getRootPath() . "p/" . urlencode($releaseName) . "/" . urlencode($review["version"]) ?>"><?= $showRelease ? htmlspecialchars($releaseName) : "" ?></a>
                            </h5></div>
                        <div id="reviewer" value="<?= $review["author"] ?>" class="review-header">
                            <h6><?= $review["author"] ?></h6> : <?= htmlspecialchars(date('d M', $review["created"])) ?>
                            <?php if($review["author"] == $user || Poggit::getAdmlv($user) > Poggit::MODERATOR) { ?>
                                <div class="action review-delete" onclick="deleteReview(this)"
                                     value="<?= $review["releaseId"] ?>">x
                                </div>
                            <?php } ?>
                        </div>
                        <div class="review-panel-left">
                            <div class="review-score review-info"><?= $review["score"] ?>/5</div>
                            <div
                                    class="review-type review-info"><?= PluginRelease::$REVIEW_TYPE[$review["type"]] ?></div>
                            <!--                        <div class="review-cat review-info">Category: <?= $review["cat"] ?></div>-->
                            <div <?= Poggit::getAdmlv($review["author"]) < Poggit::MODERATOR ? "hidden='true'" : "" ?>
                                    id="criteria" class="review-criteria review-info"
                                    value="<?= $review["criteria"] ?? 0 ?>"><?= PluginRelease::$CRITERIA_HUMAN[$review["criteria"] ?? 0] ?></div>
                        </div>
                    </div>
                    <div class="review-panel-right plugin-info">
                        <span class="review-textarea"><?= htmlspecialchars($review["message"]) ?></span>
                    </div>
                </div>
                <?php
            }
        }
    }
}
