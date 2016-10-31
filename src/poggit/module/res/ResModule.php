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

namespace poggit\module\res;

use poggit\module\Module;
use poggit\Poggit;
use poggit\session\SessionUtils;
use const poggit\RES_DIR;

class ResModule extends Module {
    static $TYPES = [
        "html" => "text/html",
        "css" => "text/css",
        "js" => "application/javascript",
        "json" => "application/json",
        "png" => "image/png",
        "ico" => "image/x-icon",
    ];
    static $BANNED = [
        "banned"
    ];

    public function getName() : string {
        return "res";
    }

    protected function resDir() : string {
        return RES_DIR;
    }

    public function output() {
        $resDir = $this->resDir();
        $path = realpath($resDir . $this->getQuery());
        if(isset(self::$BANNED[$this->getQuery()])) {
            $this->errorAccessDenied();
        }
        if($this->getQuery() === "defaultPluginIcon") {
            $this->defaultPluginIcon();
            return;
        }
        if(realpath(dirname($path)) === realpath($resDir) and is_file($path)) {
            $ext = substr($path, (strrpos($path, ".") ?: -1) + 1);
            header("Content-Type: " . self::$TYPES[$ext]);
            $cont = file_get_contents($path);
            $cont = preg_replace_callback('@\$\{([a-zA-Z0-9_\.\-:\(\)]+)\}@', function ($match) {
                return $this->translateVar($match[1]);
            }, $cont);
            echo $cont;
        } else {
            $this->errorNotFound();
        }
    }

    protected function translateVar(string $key) {
        if($key === "path.relativeRoot") {
            return Poggit::getRootPath();
        }
        if($key === "app.clientId") {
            return Poggit::getSecret("app.clientId");
        }
        if($key === "session.antiForge") {
            return SessionUtils::getInstance()->getAntiForge();
        }
        if($key === "session.isLoggedIn") {
            return SessionUtils::getInstance()->isLoggedIn() ? "true" : "false";
        }
        if($key === "meta.isDebug") {
            return Poggit::isDebug() ? "true" : "false";
        }
        return '${' . $key . '}';
    }

    private function defaultPluginIcon() {
        header("Content-Type: image/png");
        $icon = imagecreatetruecolor(96, 96);
        imagefill($icon, 0, 0, imagecolorallocate($icon, 0, 0, 0));
        imagettftext($icon, 80, 0, 20, 84, imagecolorallocate($icon, 255, 255, 255), \poggit\SECRET_PATH . "defaultFont.ttf", "?");
        imagepng($icon);
        imagedestroy($icon);
    }
}
