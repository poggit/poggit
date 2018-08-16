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

namespace poggit\utils\internet;

use poggit\Meta;
use function json_encode;

final class Discord {
    public static function errorHook(string $message, string $username = "Poggit Errors") {
        self::hook((string) Meta::getSecret("discord.errorHook"), $message, $username);
    }

    public static function pluginUpdatesHook(string $message, string $username = "Plugin Updates", array $embeds = []) {
        self::hook((string) Meta::getSecret("discord.pluginUpdatesHook"), $message, $username, $embeds);
    }

    public static function newBuildsHook(string $message, string $username = "Poggit-CI", array $embeds = []) {
        self::hook((string) Meta::getSecret("discord.newBuildsHook"), $message, $username, $embeds);
    }

    public static function auditHook(string $message, string $username, array $embeds = []) {
        self::hook((string) Meta::getSecret("discord.reviewHook"), $message, $username, $embeds);
    }

    private static function hook(string $hook, string $message, string $username, array $embeds = []) {
        $result = Curl::curlPost($hook, json_encode([
            "username" => $username,
            "content" => $message,
            "embeds" => $embeds,
        ]));
        if(Curl::$lastCurlResponseCode >= 400) {
            Meta::getLog()->e("Error executing discord webhook: " . $result);
            Meta::getLog()->e((new \Exception)->getTraceAsString());
        }
    }
}
