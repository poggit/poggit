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

use poggit\module\Module;
use poggit\Poggit;
use poggit\timeline\WelcomeTimeLineEvent;
use poggit\utils\internet\CurlUtils;
use poggit\utils\internet\MysqlUtils;

class GitHubLoginCallbackModule extends Module {
    public function getName(): string {
        return "webhooks.gh.app";
    }

    public function output() {
        $session = SessionUtils::getInstance();
        if($session->getAntiForge() !== ($_REQUEST["state"] ?? "this should never match")) {
            $this->errorAccessDenied("Please enable cookies.");
            return;
        }
        $result = CurlUtils::curlPost("https://github.com/login/oauth/access_token", [
            "client_id" => Poggit::getSecret("app.clientId"),
            "client_secret" => Poggit::getSecret("app.clientSecret"),
            "code" => $_REQUEST["code"]
        ], "Accept: application/json");
        $data = json_decode($result);
        if(CurlUtils::$lastCurlResponseCode >= 400 or !is_object($data)) {
            throw new \UnexpectedValueException($result);
        }
        if(!isset($data->access_token)) {
            // expired access token
            Poggit::redirect("");
        }

        $token = $data->access_token;
        $udata = CurlUtils::ghApiGet("user", $token);
        $name = $udata->login;
        $uid = (int) $udata->id;

        $rows = MysqlUtils::query("SELECT opts FROM users WHERE uid = ?", "i", $uid);
        if(count($rows) === 0) {
            $opts = "{}";
            MysqlUtils::query("INSERT INTO users (uid, name, token, opts) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE name = ?",
                "isss", $uid, $name, $token, $opts, $name);
        } else {
            MysqlUtils::query("UPDATE users SET name = ?, token = ? WHERE uid = ?",
                "ssi", $name, $token, $uid);
            $opts = $rows[0]["opts"];
        }

        $session->login($uid, $name, $token, json_decode($opts));
        Poggit::getLog()->w("Login success: $name ($uid)");
        $welcomeEvent = new WelcomeTimeLineEvent();
        $welcomeEvent->jointime = new \DateTime();
        $welcomeEvent->dispatchFor($uid);
        Poggit::redirect($session->removeLoginLoc(), true);
    }
}
