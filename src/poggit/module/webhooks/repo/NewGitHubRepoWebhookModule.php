<?php

/*
 * Poggit
 *
 * Copyright (C) 2016 Poggit
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

namespace poggit\module\webhooks\repo;

use poggit\module\Module;
use poggit\Poggit;
use poggit\utils\MysqlUtils;
use poggit\utils\OutputManager;

class NewGitHubRepoWebhookModule extends Module {
    static $HANDLER = [
        "ping" => PingHandler::class,
        "push" => PushHandler::class,
        "pull_request" => PullRequestHandler::class,
        "repository" => RepositoryEventHandler::class,
    ];

    public static function extPath() {
        return Poggit::getSecret("meta.extPath") . "webhooks.gh.repo";
    }

    public function getName(): string {
        return "webhooks.gh.repo";
    }

    public function output() {
        set_time_limit(150); // TODO for some projects, manually increase it
        try {
            $this->output0();
        } catch(StopWebhookExecutionException $e) {
            if($e->getCode() !== 2) echo $e->getMessage();
            if($e->getCode() >= 1) Poggit::getLog()->w($e->getMessage());
        }
    }

    private function output0() {
        OutputManager::$plainTextOutput = true;
        header("Content-Type: text/plain");

        $header = $_SERVER["HTTP_X_HUB_SIGNATURE"] ?? "invalid string";
        if(strpos($header, "=") === false) $this->wrongSig("Malformed signature header");
        list($algo, $sig) = explode("=", $header, 2);
        if($algo !== "sha1") Poggit::getLog()->w($_SERVER["HTTP_X_HUB_SIGNATURE"] . " uses $algo instaed of sha1 as hash algo");

        $webhookKey = $this->getQuery();
        // step 1: sanitize webhook key
        // NOTE this line should be changed if webhookKey is changed from BINARY(8)
        if(!preg_match('/^[0-9a-f]{16}$/i', $webhookKey)) $this->wrongSig("Invalid webhookKey");
        // step 2: hash check
        $expected = hash_hmac($algo, Poggit::getInput(), Poggit::getSecret("meta.hookSecret") . $webhookKey);
        if(!hash_equals($expected, $sig)) $this->wrongSig("Wrong signature");
        // step 3: check against repo; do this after hash check to prevent time attack
        $rows = MysqlUtils::query("SELECT repoId FROM repos WHERE webhookKey = ?", "s", hex2bin($webhookKey));
        if(count($rows) === 0) $this->wrongSig("Unknown webhookKey");
        assert(count($rows) === 1, "the 1 / 1.845E+19 probability that the same webhookKey is generated came true!");
        $assertRepoId = (int) $rows[0]["repoId"];

        $payload = json_decode(Poggit::getInput());
        if(json_last_error() !== JSON_ERROR_NONE) {
            throw new StopWebhookExecutionException("Invalid JSON: " . json_last_error_msg() . ", input data:\n" .
                json_encode(Poggit::getInput(), JSON_UNESCAPED_SLASHES), 1);
        }

        if(isset(self::$HANDLER[$event = $_SERVER["HTTP_X_GITHUB_EVENT"] ?? "invalid string"])) {
            $class = self::$HANDLER[$event];
            /** @var RepoWebhookHandler $handler */
            $handler = new $class;
            $handler->data = $payload;
            $handler->assertRepoId = $assertRepoId;
            $handler->handle();
        } else {
            throw new StopWebhookExecutionException("Unsupported GitHub event", 1);
        }
    }

    private function wrongSig(string $message) {
        http_response_code(403);
        echo "Wrong signature\n";
        throw new StopWebhookExecutionException("$message from " . Poggit::getClientIP(), 2);
    }
}
