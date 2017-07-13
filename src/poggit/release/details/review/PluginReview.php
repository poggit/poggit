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
}
