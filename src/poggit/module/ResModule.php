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

namespace poggit\module;

use poggit\account\Session;
use poggit\Config;
use poggit\Meta;
use const poggit\JS_DIR;
use const poggit\RES_DIR;

class ResModule extends Module {
    public static $TYPES = [
        "html" => "text/html",
        "css" => "text/css",
        "js" => "application/javascript",
        "json" => "application/json",
        "png" => "image/png",
        "ico" => "image/x-icon",
        "map" => "text/css",
        "phar" => "application/octet-stream",
        "sh" => "text/x-shellscript",
        "php" => "text/x-php",
    ];
    public static $BANNED = [
        "banned"
    ];

    public function getName(): string {
        return "res";
    }

    public function getAllNames(): array {
        return ["res", "js"];
    }

    public function output(): void {
        $query = $this->getQuery();

        if($query === "session.js") {
            header("Content-Type: application/json");
            header("Cache-Control: private, max-age=86400");
            ResModule::echoSessionJs(false);
            return;
        }
        if($hasSha = preg_match(/** @lang RegExp */
            '%/[a-f0-9]{7}$%', $query)) {
            $query = substr($query, 0, -8);
        }

        $resDir = Meta::getModuleName() === "js" ? JS_DIR : RES_DIR;

        if(isset(ResModule::$BANNED[$query])) $this->errorAccessDenied();

        $path = realpath($resDir . $query);
        if((realpath(dirname($path)) === realpath($resDir) || realpath(dirname($path, 2)) === realpath($resDir)) and is_file($path)) {
            $ext = strtolower(array_slice(explode(".", $path), -1)[0]);
            header("Content-Type: " . (ResModule::$TYPES[$ext] ?? "text/plain"));
            if($hasSha) {
                header("Cache-Control: public, max-age=604800");
            }
            readfile($path);
        } else {
            $this->errorNotFound();
        }
    }

    protected function translateVar(string $key) {
        if($key === "path.relativeRoot") return Meta::root();
        if($key === "app.clientId") return Meta::getSecret("app.clientId");
        if($key === "session.antiForge") return Session::getInstance(false)->getAntiForge();
        if($key === "session.isLoggedIn") return Session::getInstance(false)->isLoggedIn() ? "true" : "false";
        if($key === "session.loginName") return Session::getInstance(false)->getName();
        if($key === "session.adminLevel") return Meta::getAdmlv(Session::getInstance(false)->getName());
        if($key === "meta.isDebug") return Meta::isDebug() ? "true" : "false";
        return '${' . $key . '}';
    }

    public static function echoSessionJs(bool $html = false) {
        if($html) echo '<script>';
        echo 'var sessionData = ';
        echo json_encode([
            "path" => ["relativeRoot" => Meta::root()],
            "app" => ["clientId" => Meta::getSecret("app.clientId")],
            "session" => [
                "antiForge" => Session::getInstance(false)->getAntiForge(),
                "isLoggedIn" => Session::getInstance(false)->isLoggedIn(),
                "loginName" => Session::getInstance(false)->getName(),
                "adminLevel" => Meta::getAdmlv(Session::getInstance(false)->getName())
            ],
            "meta" => ["isDebug" => Meta::isDebug()],
        ], JSON_UNESCAPED_SLASHES);
        echo ";\n";
        echo 'var PoggitConsts = ';
        echo json_encode([
            "AdminLevel" => [
                "GUEST" => Meta::ADMLV_GUEST,
                "MEMBER" => Meta::ADMLV_MEMBER,
                "CONTRIBUTOR" => Meta::ADMLV_CONTRIBUTOR,
                "MODERATOR" => Meta::ADMLV_MODERATOR,
                "REVIEWER" => Meta::ADMLV_REVIEWER,
                "ADM" => Meta::ADMLV_ADMIN,
            ],
            "Config" => (new \ReflectionClass(Config::class))->getConstants(),
        ]);
        echo ";\n";
        if($html) echo '</script>';
    }
}
