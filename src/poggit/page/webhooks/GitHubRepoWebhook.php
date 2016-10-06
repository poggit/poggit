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

namespace poggit\page\webhooks;

use poggit\page\Page;
use poggit\Poggit;
use function poggit\getInput;

class GitHubRepoWebhook extends Page {
    public static function extPath() {
        return Poggit::getSecret("meta.extPath") . "webhooks.gh.repo";
    }

    public function getName() : string {
        return "webhooks.gh.repo";
    }

    public function output() {
        Poggit::$plainTextOutput = true;
        header("Content-Type: text/plain");
        $headers = apache_request_headers();
        $input = getInput();
        $sig = $headers["X-Hub-Signature"] ?? "this should never match";
        if(strpos($sig, "=") === false) {
            $this->wrongSignature($sig);
            return;
        }
        list($algo, $recvHash) = explode("=", $sig, 2);
        $expectedHash = hash_hmac($algo, $input, Poggit::getSecret("meta.hookSecret"));
        if(!hash_equals($expectedHash, $recvHash)) {
            $this->wrongSignature($sig);
            return;
        }
        $payload = json_decode($input);
        switch($headers["X-GitHub-Event"]) {
            case "ping":
                echo "PONG!\n";
                return;
            case "push":
                $handler = new PushWebhookHandler($payload);
//                $this->onPush($payload);
                break;
            default:
                // TODO error
                echo "Unsupported hook!\n";
                return;
        }
        echo "Using handler: " . get_class($handler) . "\n";
        try {
            $handler->handle();
        } catch(\RuntimeException $e) {
            http_response_code(400);
            echo $handler->resultMessage . "\n";
            echo $e->getMessage() . "\n";
            echo $e->getTraceAsString() . "\n";
            return;
        }
    }

    private function wrongSignature(string $signature) {
        Poggit::getLog()->w("Wrong webhook secret $signature from " . $_SERVER["REMOTE_ADDR"]);
        http_response_code(403);
        return;
    }
}
