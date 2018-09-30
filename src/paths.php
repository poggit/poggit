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

namespace poggit;

use function define;
use function defined;
use function is_file;
use function spl_autoload_register;
use function str_replace;
use const DIRECTORY_SEPARATOR;
use const PHP_MAJOR_VERSION;
use const PHP_MINOR_VERSION;

if(!defined('poggit\INSTALL_PATH')) define('poggit\INSTALL_PATH', POGGIT_INSTALL_PATH);
if(!defined('poggit\SOURCE_PATH')) define('poggit\SOURCE_PATH', INSTALL_PATH . "src" . DIRECTORY_SEPARATOR);
if(!defined('poggit\LIBS_PATH')) define('poggit\LIBS_PATH', INSTALL_PATH . "libs" . DIRECTORY_SEPARATOR);
if(!defined('poggit\SECRET_PATH')) define('poggit\SECRET_PATH', INSTALL_PATH . "secret" . DIRECTORY_SEPARATOR);
if(!defined('poggit\ASSETS_PATH')) define('poggit\ASSETS_PATH', INSTALL_PATH . "assets" . DIRECTORY_SEPARATOR);
if(!defined('poggit\RES_DIR')) define('poggit\RES_DIR', INSTALL_PATH . "res" . DIRECTORY_SEPARATOR);
if(!defined('poggit\RESOURCE_DIR')) define('poggit\RESOURCE_DIR', INSTALL_PATH . "resources" . DIRECTORY_SEPARATOR);
if(!defined('poggit\JS_DIR')) define('poggit\JS_DIR', INSTALL_PATH . "js" . DIRECTORY_SEPARATOR);
if(!defined('poggit\LOG_DIR')) define('poggit\LOG_DIR', INSTALL_PATH . "logs" . DIRECTORY_SEPARATOR);

require POGGIT_INSTALL_PATH . "vendor/autoload.php";

spl_autoload_register(function(string $class) {
    $bases = [SOURCE_PATH . str_replace("\\", DIRECTORY_SEPARATOR, $class)];
    $extensions = [".php" . PHP_MAJOR_VERSION . PHP_MINOR_VERSION, ".php" . PHP_MAJOR_VERSION, ".php"];
    foreach($extensions as $ext) {
        foreach($bases as $base) {
            $file = $base . $ext;
            if(is_file($file)) {
//                file_put_contents("php://stderr", "Autoload $class\n");
//                file_put_contents("php://stderr", "Stack: " . (new \Exception)->getTraceAsString() . "====\n");

                require_once $file;
                return;
            }
        }
    }
});
