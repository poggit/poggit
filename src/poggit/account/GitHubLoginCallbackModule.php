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

namespace poggit\account;

use DateTime;
use poggit\Meta;
use poggit\module\Module;
use poggit\timeline\WelcomeTimeLineEvent;
use poggit\utils\internet\Curl;
use poggit\utils\internet\Discord;
use poggit\utils\internet\GitHub;
use poggit\utils\internet\Mysql;
use UnexpectedValueException;
use function array_search;
use function count;
use function explode;
use function implode;
use function in_array;
use function is_object;
use function json_decode;
use function time;

class GitHubLoginCallbackModule extends Module {
    public function output() {
        Session::$CHECK_AUTO_LOGIN = false;
        $session = Session::getInstance();
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
            throw new UnexpectedValueException($result);
        }
        if(!isset($data->access_token)) {
            // expired access token
            Meta::redirect();
        }

        $token = $data->access_token;
        $userData = GitHub::ghApiGet("user", $token);
        $name = $userData->login;
        $uid = (int) $userData->id;
        $scopes = $data->scope;
        $scopesArray = explode(",", $scopes);
        if(!in_array("user:email", $scopesArray, true)) {
            $this->errorAccessDenied("You did not enable the user:email scope.");
        }
        $noMailScopes = $scopesArray;
        unset($noMailScopes[array_search("user:email", $noMailScopes, true)]);
        Session::setCookie("ghScopes", implode(",", $noMailScopes));

        $email = $userData->email ?? "";
        if($email === "") {
            $email = GitHub::ghApiGet("user/emails", $token)[0] ?? (object) ["email" => ""];
            $email = $email->email ?? "";
        }

        $rows = Mysql::query("SELECT UNIX_TIMESTAMP(lastLogin) lastLogin, UNIX_TIMESTAMP(lastNotif) lastNotif, opts
                FROM users WHERE uid = ?", "i", $uid);
        if(count($rows) === 0) {
            $opts = "{}";
            Mysql::query("INSERT INTO users (uid, name, token, scopes, email, opts) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name = ?",
                "issssss", $uid, $name, $token, $scopes, $email ?? "", $opts, $name);
            $lastLogin = time();
            $lastNotif = time();
            Discord::regHook("$name #$uid [" . Meta::getClientIP() . "] $email");
        } else {
            Mysql::query("UPDATE users SET name = ?, token = ?, scopes = ?, email = ?, lastLogin = CURRENT_TIMESTAMP WHERE uid = ?", "ssssi", $name, $token, $scopes, $email ?? "", $uid);
            $opts = $rows[0]["opts"];
            $lastLogin = (int) $rows[0]["lastLogin"];
            $lastNotif = (int) $rows[0]["lastNotif"];
        }

        $session->login($uid, $name, $token, $scopesArray, $lastLogin, $lastNotif, json_decode($opts));
        Meta::getLog()->w("Login success: $name ($uid)");
        $welcomeEvent = new WelcomeTimeLineEvent();
        $welcomeEvent->jointime = new DateTime();
        $welcomeEvent->dispatchFor($uid);
        Meta::redirect($session->removeLoginLoc(), true);
    }
}
