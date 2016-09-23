<?php

/*
 * Copyright 2016 poggit
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

namespace poggit\page\webhooks;

use poggit\page\Page;
use stdClass;
use function poggit\getDb;
use function poggit\getInput;
use function poggit\getLog;
use function poggit\getSecret;

/**
 * Class GitHubWebhook
 *
 * @package poggit\page\webhooks
 * @deprecated
 */
class GitHubWebhook extends Page {
    static $ACCOUNT_TYPES = [
        "User" => 1,
        "Organization" => 2
    ];

    public function getName() : string {
        return "webhooks.gh.in";
    }

    public function output() {
        $headers = apache_request_headers();
        $sig = $headers["X-Hub-Signature"] ?? "(none provided)";
        if(strpos($sig, "=") === false) {
            $this->wrongSignature($sig);
            return;
        }
        list($algo, $recvHash) = explode("=", $sig, 2);
        $shouldHash = hash_hmac($algo, getInput(), getSecret("integration.hookSecret"));
        if(!hash_equals($shouldHash, $recvHash)) {
            $this->wrongSignature($sig);
            return;
        }
        $input = json_decode(getInput());
        switch($input->action) {
            case "created":
                $this->onCreate($input);
        }
    }

    private function wrongSignature(string $signature) {
        getLog()->w("Wrong webhook secret $signature from " . $_SERVER["REMOTE_ADDR"]);
        http_response_code(403);
        return;
    }

    private function onCreate(stdClass $input) {
        $installation = $input->installation;
        $installId = $installation->id;
        $account = $installation->account;
        $name = $account->login;
        $userId = $account->id;
        $type = self::$ACCOUNT_TYPES[$account->type];

        $db = getDb();
        $stmt = $db->prepare("UPDATE installs SET name=CONCAT('(UNKNOWN)', uid) WHERE uid != ? AND name = ?"); // Alice is renamed to Bob, and a new account called Alice registers
        $stmt->bind_param("is", $userId, $name);
        $stmt->execute();
        if($stmt->error) {
            throw new \RuntimeException($stmt->error);
        }
        if($stmt->affected_rows > 0) {
            getLog()->w("Renamed $stmt->affected_rows row(s) with name=$name");
        }

        $stmt = $db->prepare("INSERT INTO installs (uid, name, installId, type) VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE name=?, installId=?, type=?");
        if($stmt === false) {
            getLog()->e($db->error);
            die;
        }
        $stmt->bind_param("isiisii", $userId, $name, $installId, $type, $name, $installId, $type);
        $stmt->execute();
        if($stmt->error) {
            throw new \RuntimeException($stmt->error);
        }
    }
}
