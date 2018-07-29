<?php

/*
 * Poggit
 *
 * Copyright (C) 2016-2018 Poggit
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
use poggit\Meta;
use poggit\module\AjaxModule;
use poggit\utils\internet\GitHub;
use poggit\utils\internet\Mysql;
use function http_response_code;
use function strlen;

class ReviewReplyAjax extends AjaxModule {
    protected function impl() {
        $reviewId = (int) $this->param("reviewId");
        $message = $this->param("message");
        if(strlen($message) > 8000) $this->errorBadRequest("Message is too long");
        $isDelete = $message === "";
        $userId = Session::getInstance()->getUid();

        $info = Mysql::query("SELECT p.repoId, IF(rrr.reviewId IS NULL, 0, 1) hasOld FROM release_reviews rr
            LEFT JOIN release_reply_reviews rrr ON rr.reviewId = rrr.reviewId AND rrr.user = ?
            INNER JOIN releases r ON rr.releaseId = r.releaseId
            INNER JOIN projects p ON r.projectId = p.projectId
            WHERE rr.reviewId = ?", "ii", $userId, $reviewId);
        if(!isset($info[0])) $this->errorBadRequest("No such review");
        $repoId = (int) $info[0]["repoId"];
        if(!self::mayReplyTo($repoId)) {
            $this->errorBadRequest("You must have push access to the repo to reply to this review");
        }

        $hasOld = (bool) (int) $info[0]["hasOld"];
        if(!$hasOld and $isDelete) {
            http_response_code(204);
            return;
        }

        if(!$isDelete) {
            Mysql::query($hasOld ? "UPDATE release_reply_reviews SET message = ? WHERE reviewId = ? AND user = ?" :
                "INSERT INTO release_reply_reviews (message, reviewId, user) VALUES (?, ?, ?)", "sii", $message, $reviewId, $userId);
        } else {
            Mysql::query("DELETE FROM release_reply_reviews WHERE reviewId = ? AND user = ?", "ii", $reviewId, $userId);
        }
    }

    public static function mayReplyTo(int $repoId): bool {
        $session = Session::getInstance();
        return Meta::getAdmlv() >= Meta::ADMLV_MODERATOR or
            GitHub::testPermission($repoId, $session->getAccessToken(), $session->getName(), "push");
    }
}
