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

namespace poggit\module;

use poggit\account\Session;
use poggit\ci\builder\ProjectBuilder;
use poggit\ci\lint\BuildResult;
use poggit\Config;
use poggit\Meta;
use poggit\release\Release;
use poggit\utils\lang\Lang;
use ReflectionClass;
use stdClass;
use function array_slice;
use function dirname;
use function explode;
use function header;
use function implode;
use function in_array;
use function is_file;
use function json_encode;
use function preg_match;
use function readfile;
use function realpath;
use function strtolower;
use function substr;
use const JSON_UNESCAPED_SLASHES;
use const poggit\JS_DIR;
use const poggit\RES_DIR;

class ResModule extends Module {
    const TYPES = [
        "html" => "text/html",
        "css" => "text/css",
        "map" => "text/css",
        "js" => "application/javascript",
        "json" => "application/json",
        "png" => "image/png",
        "svg" => "image/svg+xml",
        "ico" => "image/x-icon",
        "sh" => "text/x-shellscript",
        "php" => "text/x-php",
        "phar" => "application/octet-stream",
    ];
    const BANNED = [
        "banned"
    ];

    public function output() {
        $query = $this->getQuery();

        $pieces = explode("/", $query);
        if(Lang::endsWith($pieces[0], ".css") && Lang::endsWith($query, ".png")) {
            $query = implode("/", array_slice($pieces, 1));
        }

        if($hasSalt = preg_match(/** @lang RegExp */
            '%/[a-f0-9]{7}$%', $query)) {
            $query = substr($query, 0, -8);
        }

        $resDir = Meta::getModuleName() === "js" ? JS_DIR : RES_DIR;

        if(isset(self::BANNED[$query])) $this->errorAccessDenied();

        $path = realpath($resDir . $query);
        if(is_file($path) and (realpath(dirname($path)) === realpath($resDir) || realpath(dirname($path, 2)) === realpath($resDir))) {
            $ext = strtolower(array_slice(explode(".", $path), -1)[0]);
            header("Content-Type: " . (self::TYPES[$ext] ?? "text/plain"));
            if(!isset(self::TYPES[$ext])) Meta::getLog()->w("Undefined content-type for $ext");
            $maxAge = $hasSalt || in_array($ext, ["ico", "png"], true) ? 2592000 : 86400;
            header("Cache-Control: public, max-age=$maxAge");
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
                "antiForge" => Session::getInstance()->getAntiForge(), // TODO fix session initialization problem
                "isLoggedIn" => Session::getInstance()->isLoggedIn(),
                "loginName" => Session::getInstance()->getName(),
                "adminLevel" => Meta::getAdmlv(Session::getInstance()->getName())
            ],
            "opts" => Session::getInstance()->getOpts() ?? new stdClass(),
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
            "Staff" => Meta::getStaffList(),
            "BuildClass" => ProjectBuilder::$BUILD_CLASS_HUMAN,
            "LintLevel" => (object) BuildResult::$names,
            "Config" => (new ReflectionClass(Config::class))->getConstants(),
            "ReleaseState" => Release::$STATE_SID_TO_ID,
        ]);
        echo ";\n";
        if($html) echo '</script>';
    }
}
