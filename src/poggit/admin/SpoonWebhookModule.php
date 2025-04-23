<?php

/*
 * poggit
 *
 * Copyright (C) 2018 SOFe
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

declare(strict_types=1);

namespace poggit\admin;

use poggit\Meta;
use poggit\module\Module;
use poggit\utils\PocketMineApi;

class SpoonWebhookModule extends Module {
    public function output() {
        // only allow POST method.
        if($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo "Method Not Allowed";
            return;
        }

        // verify the request token.
        $raw_secret = Meta::getSecret("meta.pocketmineSecret");
        $hash = $_SERVER["HTTP_X_HUB_SIGNATURE_256"];
        $body = file_get_contents("php://input");
        $expected = "sha256=" . hash_hmac("sha256", $body, $raw_secret);
        if(!hash_equals($expected, $hash)) {
            http_response_code(403);
            echo "Forbidden";
            return;
        }

        // decode payload.
        $data = json_decode($body);
        if($data === null || json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo "Bad Request";
            return;
        }

        // verify the payload is event we need.
        if($data->action !== "published" || $data->release->draft !== false) {
            http_response_code(200);
            echo "Ignoring, not a published release.";
            return;
        }

        // handle the payload.
        Meta::getLog()->i("Handling spoon add webhook event from GitHub.");
        $version = $data->release->tag_name;
        $latest = PocketMineApi::$LATEST;

        // Ignore any versions that has a prefix or suffix.
        if(str_contains($version, "-") || str_contains($version, "+")) {
            http_response_code(200);
            echo "PocketMine API $version is ignored, suffix/prefix detected.";
            return;
        }

        // If the version is already added or before latest, return.
        if(version_compare($version, $latest, "<=")) {
            http_response_code(200);
            echo "PocketMine API $version is already added or before the latest version.";
            return;
        }

        // Incompatible if the version is the next major.
        $incompatible = (int)explode(".", $version)[0] > (int)explode(".", $latest)[0];
        $id = SpoonAddAjax::addSpoon($version, $incompatible ? 1 : 0);

        // Complete.
        Meta::getLog()->i("PocketMine API $version has been added to Poggit.");
        echo "PocketMine API $version has been added to Poggit.\nID: $id\nIncompatible: " . ($incompatible ? "true" : "false");
    }
}
