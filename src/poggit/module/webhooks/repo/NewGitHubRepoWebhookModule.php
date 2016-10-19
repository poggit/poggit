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
use function poggit\getInput;

class NewGitHubRepoWebhookModule extends Module {
    static $HANDLER = [
        "ping" => PingHandler::class,
        "push" => PushHandler::class,
    ];

    public static function extPath() {
        return Poggit::getSecret("meta.extPath") . "webhooks.gh.repo";
    }

    public function getName() : string {
        return "webhooks.gh.repo";
    }

    public function output() {
        try {
            $this->output0();
        } catch(StopWebhookExecutionException $e) {
            if($e->getCode() !== 2) echo $e->getMessage();
            if($e->getCode() >= 1) Poggit::getLog()->w($e->getMessage());
        }
    }

    private function output0() {
        Poggit::$plainTextOutput = true;
        header("Content-Type: text/plain");

        $header = $_SERVER["HTTP_X_HUB_SIGNATURE"] ?? "invalid string";
        if(strpos($header, "=") !== false) $this->wrongSig();
        list($algo, $sig) = explode("=", $header, 2);
        if($algo !== "sha1") Poggit::getLog()->w($_SERVER["HTTP_X_HUB_SIGNATURE"] . " uses $algo instaed of sha1 as hash algo");
        $expected = hash_hmac($algo, getInput(), Poggit::getSecret("meta.hookSecret") . $this->getQuery());
        if(!hash_equals($expected, $sig)) $this->wrongSig();

        $payload = json_decode(getInput());
        if(json_last_error() !== JSON_ERROR_NONE) {
            throw new StopWebhookExecutionException("Invalid JSON: " . json_last_error_msg() . ", input data:\n" .
                json_encode(getInput(), JSON_UNESCAPED_SLASHES), 1);
        }

        if(isset(self::$HANDLER[$event = $_SERVER["HTTP_X_GITHUB_EVENT"] ?? "invalid string"])) {
            $class = self::$HANDLER[$event];
            /** @var RepoWebhookHandler $handler */
            $handler = new $class;
            $handler->data = $payload;
            $handler->handle();
        } else {
            throw new StopWebhookExecutionException("Unsupported GitHub event", 1);
        }
    }

    private function wrongSig() {
        http_response_code(403);
        throw new StopWebhookExecutionException("Wrong signature from " . $_SERVER["REMOTE_ADDR"], 1);
    }
}
