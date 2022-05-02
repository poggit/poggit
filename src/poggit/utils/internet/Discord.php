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

use function min;
use poggit\Meta;
use function json_encode;
use function strlen;
use function substr;

final class Discord {
    private static $errorRecursion = false;

    public static function errorHook(string $message, string $username = "Poggit Errors") {
        if(self::$errorRecursion){
            return;
        }
        self::$errorRecursion = true;
        self::hook((string) Meta::getSecret("discord.errorHook"), $message, $username);
        self::$errorRecursion = false;
    }

    public static function pluginUpdatesHook(string $message, string $username = "Plugin Updates", array $embeds = []) {
        self::hook((string) Meta::getSecret("discord.pluginUpdatesHook"), $message, $username, $embeds);
    }

    public static function newBuildsHook(string $message, string $username = "Poggit-CI", array $embeds = []) {
        self::hook((string) Meta::getSecret("discord.newBuildsHook"), $message, $username, $embeds);
    }

    public static function reviewsHook(string $message, string $username, array $embeds = []) {
        self::hook((string) Meta::getSecret("discord.reviewsHook"), $message, $username, $embeds);
    }

    public static function auditHook(string $message, string $username, array $embeds = []) {
        self::hook((string) Meta::getSecret("discord.reviewHook"), $message, $username, $embeds);
    }

    public static function throttleHook(string $message, string $username, array $embeds = []) {
        self::hook((string) Meta::getSecret("discord.throttleHook"), $message, $username, $embeds);
    }

    private static function hook(string $hook, string $message, string $username, array $embeds = []) {
        $length = strlen($message);

        foreach($embeds as &$embed){
            foreach($embed["fields"] ?? [] as &$field){
                $length += strlen($field["value"]);
                if($length >= 1900){
                    $field["value"] = "...";
                }
            }
            /** @noinspection DisconnectedForeachInstructionInspection */
            unset($field);
        }
        unset($embed);

        $result = Curl::curlPost($hook, json_encode([
            "username" => $username,
            "content" => substr($message, 0, min(strlen($message), 1500)),
            "embeds" => $embeds,
        ]), "Content-Type: application/json");
        if(Curl::$lastCurlResponseCode >= 400) {
            Meta::getLog()->e("Error executing discord webhook: " . $result);
            Meta::getLog()->e((new \Exception)->getTraceAsString());
        }
    }

    public static function regHook(string $message, string $username = "Unwelcome Registrations", array $embeds = []) {
        self::hook((string) Meta::getSecret("discord.regHook"), $message, $username, $embeds);
    }
}
