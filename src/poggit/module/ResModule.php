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
use poggit\utils\lang\Lang;
use const poggit\JS_DIR;
use const poggit\RES_DIR;

class ResModule extends Module {
    static $TYPES = [
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
    static $BANNED = [
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
            ResModule::echoSessionJs(false);
            return;
        }

        $resDir = Meta::getModuleName() === "js" ? JS_DIR : RES_DIR;

        if(Lang::startsWith($query, "revalidate-")) $query = substr($query, strlen("revalidate-"));
        if(isset(self::$BANNED[$query])) $this->errorAccessDenied();

        $path = realpath($resDir . $query);
        if((realpath(dirname($path)) === realpath($resDir) || realpath(dirname(dirname($path))) === realpath($resDir)) and is_file($path)) {
            $ext = strtolower(array_slice(explode(".", $path), -1)[0]);
            header("Content-Type: " . self::$TYPES[$ext]);
            if(!Meta::isDebug() || strpos($query, ".min.") !== false || $ext === "png" || $ext === "ico") header("Cache-Control: private, max-age=86400");
//            $cont = file_get_contents($path);
//            $cont = preg_replace_callback('@\$\{([a-zA-Z0-9_\.\-:\(\)]+)\}@', function ($match) {
//                return $this->translateVar($match[1]);
//            }, $cont);
//            echo $cont;
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
        if($key === "session.adminLevel") return Meta::getUserAccess(Session::getInstance(false)->getName());
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
                "adminLevel" => Meta::getUserAccess(Session::getInstance(false)->getName())
            ],
            "meta" => ["isDebug" => Meta::isDebug()],
        ], JSON_UNESCAPED_SLASHES);
        echo ";\n";
        echo 'var PoggitConsts = ';
        echo json_encode([
            "AdminLevel" => [
                "GUEST" => Meta::GUEST,
                "MEMBER" => Meta::MEMBER,
                "CONTRIBUTOR" => Meta::CONTRIBUTOR,
                "MODERATOR" => Meta::MODERATOR,
                "REVIEWER" => Meta::REVIEWER,
                "ADM" => Meta::ADM,
            ],
            "Config" => (new \ReflectionClass(Config::class))->getConstants(),
        ]);
        echo ";\n";
        if($html) echo '</script>';
    }
}
