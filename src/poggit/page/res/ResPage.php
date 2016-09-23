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

namespace poggit\page\res;

use poggit\page\Page;
use const poggit\RES_DIR;

class ResPage extends Page {
    static $TYPES = [
        "html" => "text/html",
        "css" => "text/css",
        "js" => "application/javascript",
        "json" => "application/json",
    ];

    public function getName() :string {
        return "res";
    }

    public function output() {
        $path = RES_DIR . $this->getQuery();
        if(realpath(dirname($path)) === realpath(RES_DIR)) {
            $ext = substr($path, (strrpos($path, ".") ?: -1) + 1);
            header("Content-Type: " . self::$TYPES[$ext]);
            readfile($path);
        } else {
            $this->errorNotFound();
        }
    }
}
