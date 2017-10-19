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

namespace poggit\account;

use poggit\Meta;
use poggit\module\Module;
use poggit\timeline\WelcomeTimeLineEvent;
use poggit\utils\internet\Curl;
use poggit\utils\internet\Mysql;

class GitHubLoginCallbackModule extends Module {
    public function getName(): string {
        return "webhooks.gh.app";
    }

    public function output() {
        $session = Session::getInstance(false);
        if($session->getAntiForge() !== ($_REQUEST["state"] ?? "this should never match")) {
            $this->errorAccessDenied("Please enable cookies.");
            return;
        }
        $result = Curl::curlPost("https://github.com/login/oauth/access_token", [
            "client_id" => Meta::getSecret("app.clientId"),
            "client_secret" => Meta::getSecret("app.clientSecret"),
            "code" => $_REQUEST["code"]
        ], "Accept: application/json");
        $data = json_decode($result);
        if(Curl::$lastCurlResponseCode >= 400 or !is_object($data)) {
            throw new \UnexpectedValueException($result);
        }
        if(!isset($data->access_token)) {
            // expired access token
            Meta::redirect("");
        }

        $token = $data->access_token;
        $userData = Curl::ghApiGet("user", $token);
        $name = $userData->login;
        $uid = (int) $userData->id;

        $rows = Mysql::query("SELECT UNIX_TIMESTAMP(lastLogin) lastLogin, UNIX_TIMESTAMP(lastNotif) lastNotif, opts
                FROM users WHERE uid = ?", "i", $uid);
        if(count($rows) === 0) {
            $opts = "{}";
            Mysql::query("INSERT INTO users (uid, name, token, opts) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE name = ?",
                "issss", $uid, $name, $token, $opts, $name);
            $lastLogin = time();
            $lastNotif = time();
        } else {
            Mysql::query("UPDATE users SET name = ?, token = ?, lastLogin = CURRENT_TIMESTAMP WHERE uid = ?", "ssi", $name, $token, $uid);
            $opts = $rows[0]["opts"];
            $lastLogin = (int) $rows[0]["lastLogin"];
            $lastNotif = (int) $rows[0]["lastNotif"];
        }

        $session->login($uid, $name, $token, $lastLogin, $lastNotif, json_decode($opts));
        Meta::getLog()->w("Login success: $name ($uid)");
        $welcomeEvent = new WelcomeTimeLineEvent();
        $welcomeEvent->jointime = new \DateTime();
        $welcomeEvent->dispatchFor($uid);
        Meta::redirect($session->removeLoginLoc(), true);
    }
}
