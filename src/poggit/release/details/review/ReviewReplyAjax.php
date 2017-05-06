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

use poggit\account\SessionUtils;
use poggit\module\AjaxModule;
use poggit\Poggit;
use poggit\utils\internet\CurlUtils;
use poggit\utils\internet\MysqlUtils;

class ReviewReplyAjax extends AjaxModule {
    public function getName(): string {
        return "review.reply";
    }

    protected function impl() {
        $reviewId = (int) $this->param("reviewId");
        $message = $this->param("message");
        if(strlen($message) > 8000) $this->errorBadRequest("Message is too long");
        $isDelete = strlen($message) === 0;
        $userId = SessionUtils::getInstance()->getLogin()["uid"];

        $info = MysqlUtils::query("SELECT p.repoId, IF(rrr.reviewId IS NULL, 0, 1) hasOld FROM release_reviews rr
            LEFT JOIN release_reply_reviews rrr ON rr.reviewId = rrr.reviewId AND rrr.user = ?
            INNER JOIN releases r ON rr.releaseId = r.releaseId
            INNER JOIN projects p ON r.projectId = p.projectId
            WHERE rr.reviewId = ?", "ii", $userId, $reviewId);
        if(!isset($info[0])) $this->errorBadRequest("No such review");
        $repoId = (int) $info[0]["repoId"];
        if(!ReviewReplyAjax::mayReplyTo($repoId)) {
            $this->errorBadRequest("You must have push access to the repo to reply to this review");
        }

        $hasOld = (bool) (int) $info[0]["hasOld"];
        if(!$hasOld and $isDelete) {
            http_response_code(204);
            return;
        }

        if(!$isDelete) {
            MysqlUtils::query($hasOld ? "UPDATE release_reply_reviews SET message = ? WHERE reviewId = ? AND user = ?" :
                "INSERT INTO release_reply_reviews (message, reviewId, user) VALUES (?, ?, ?)", "sii", $message, $reviewId, $userId);
        } else {
            MysqlUtils::query("DELETE FROM release_reply_reviews WHERE reviewId = ? AND user = ?", "ii", $reviewId, $userId);
        }

        echo "OK\n";
    }

    public static function mayReplyTo(int $repoId): bool {
        $session = SessionUtils::getInstance();
        return Poggit::getUserAccess() >= Poggit::MODERATOR or
            CurlUtils::testPermission($repoId, $session->getAccessToken(), $session->getName(), "push");
    }
}