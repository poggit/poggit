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
use poggit\session\SessionUtils;
use function poggit\redirect;

class GitHubAppWebhook extends Page {
    public function getName() : string {
        return "webhooks.gh.app";
    }

    public function output() {
        $session = SessionUtils::getInstance();
        if($session->getAntiForge() !== ($_REQUEST["state"] ?? "this should never match")) {
            $this->errorAccessDenied();
            return;
        }
        $result = Poggit::curlPost("https://github.com/login/oauth/access_token", [
            "client_id" => Poggit::getSecret("app.clientId"),
            "client_secret" => Poggit::getSecret("app.clientSecret"),
            "code" => $_REQUEST["code"]
        ], "Accept: application/json");
        $data = json_decode($result);
        if(!is_object($data)) {
            throw new \UnexpectedValueException($result);
        }
        if(!isset($data->access_token)) {
            // expired access token
            redirect("");
        }

        $token = $data->access_token;
        $udata = json_decode(Poggit::curlGet("https://api.github.com/user", "Authorization: bearer $token"));
        $name = $udata->login;
        $id = (int) $udata->id;

        Poggit::queryAndFetch("INSERT INTO users (uid, name, token) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE token=?",
            "isss", $id, $name, $token, $token);

        $row = Poggit::queryAndFetch("SELECT opts FROM users WHERE uid=$id")[0];
        $session->login($id, $name, $token, json_decode($row["opts"]));
        Poggit::getLog()->i("Login success: $name ($id)");
        redirect($session->removeLoginLoc(), true);
    }
}
