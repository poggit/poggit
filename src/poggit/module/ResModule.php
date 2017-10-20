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
use poggit\ci\builder\ProjectBuilder;
use poggit\ci\lint\BuildResult;
use poggit\Config;
use poggit\Meta;
use const poggit\JS_DIR;
use const poggit\RES_DIR;

class ResModule extends Module {
    const TYPES = [
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
    const BANNED = [
        "banned"
    ];

    public function getName(): string {
        return "res";
    }

    public function getAllNames(): array {
        return ["res", "js"];
    }

    public function output() {
        $query = $this->getQuery();

        if($query === "session.js") {
            header("Content-Type: application/json");
            header("Cache-Control: private, max-age=86400");
            self::echoSessionJs(false);
            return;
        }
        if(preg_match(/** @lang RegExp */
            '%/[a-f0-9]{7}$%', $query)) {
            $query = substr($query, 0, -8);
        }

        $resDir = Meta::getModuleName() === "js" ? JS_DIR : RES_DIR;

        if(isset(self::BANNED[$query])) $this->errorAccessDenied();

        $path = realpath($resDir . $query);
        if(is_file($path) and (realpath(dirname($path)) === realpath($resDir) || realpath(dirname($path, 2)) === realpath($resDir))) {
            $ext = strtolower(array_slice(explode(".", $path), -1)[0]);
            header("Content-Type: " . (self::TYPES[$ext] ?? "text/plain"));
            if(!Meta::isDebug() || strpos($query, ".min.") !== false || $ext === "png" || $ext === "ico") header("Cache-Control: private, max-age=86400");
            readfile($path);
        } else {
            $this->errorNotFound();
        }
    }

    public static function echoSessionJs(bool $html = false) {
        if($html) echo '<script>';
        echo 'var sessionData = ';
        echo json_encode([
            "path" => ["relativeRoot" => Meta::root()],
            "app" => ["clientId" => Meta::getSecret("app.clientId")],
            "session" => [
                "antiForge" => Session::getInstance(false)->getAntiForge(), // TODO fix session initialization problem
                "isLoggedIn" => Session::getInstance(false)->isLoggedIn(),
                "loginName" => Session::getInstance(false)->getName(),
                "adminLevel" => Meta::getAdmlv(Session::getInstance(false)->getName())
            ],
            "opts" => Session::getInstance(false)->getOpts() ?? new \stdClass(),
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
            "BuildClass" => ProjectBuilder::$BUILD_CLASS_HUMAN,
            "LintLevel" => (object) BuildResult::$names,
            "Config" => (new \ReflectionClass(Config::class))->getConstants(),
        ]);
        echo ";\n";
        if($html) echo '</script>';
    }
}
