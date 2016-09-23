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
use poggit\session\SessionUtils;
use function poggit\curlGet;
use function poggit\curlPost;
use function poggit\getDb;
use function poggit\getLog;
use function poggit\getSecret;
use function poggit\redirect;

class GitHubAppWebhook extends Page {
    public function getName() : string {
        return "webhooks.gh.app";
    }

    public function output() {
        $session = new SessionUtils();
        getLog()->d($session->getAppState());
        getLog()->d(json_encode($_REQUEST));
        if($session->getAppState() !== ($_REQUEST["state"] ?? "this should never match")) {
            $this->errorAccessDenied();
            return;
        }
        $result = curlPost("https://github.com/login/oauth/access_token", [
            "client_id" => getSecret("app.clientId"),
            "client_secret" => getSecret("app.clientSecret"),
            "code" => $_REQUEST["code"]
        ]);
        $data = json_decode($result);
        if(!is_object($data)) {
            throw new \UnexpectedValueException($result);
        }
        if(!isset($data->access_token)) {
            // expired access token
            redirect("");
        }

        $token = $data->access_token;
        $udata = json_decode(curlGet("https://api.github.com/user", "Authorization: bearer $token"));
        getLog()->d(json_encode($udata));
        $name = $udata->login;
        $id = (int) $udata->id;

        $db = getDb();
        $stmt = $db->prepare("INSERT INTO users (uid, name, token) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE token=?");
        $stmt->bind_param("isss", $id, $name, $token, $token);
        $stmt->execute();

        $result = $db->query("SELECT opts FROM users WHERE uid=$id");
        $row = $result->fetch_assoc();
        $result->close();
        $session->login($id, $name, $token, json_decode($row["opts"]));
        getLog()->i("Login success: $name ($id)");
        redirect("");
    }
}
