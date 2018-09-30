<?php

/*
 * Copyright 2016-2018 Poggit
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


namespace {
    if(!defined('POGGIT_INSTALL_PATH')) define('POGGIT_INSTALL_PATH', realpath(__DIR__) . DIRECTORY_SEPARATOR);

    function string_not_empty(string $string): bool {
        return $string !== "";
    }
}

namespace poggit {

    use function header;
    use function ltrim;
    use poggit\account\Session;

    use poggit\utils\lang\Lang;
    use poggit\utils\lang\NativeError;
    use poggit\utils\OutputManager;
    use function set_error_handler;
    use function strtolower;


    require_once __DIR__ . "/src/paths.php";
    set_error_handler(__NAMESPACE__ . "\\error_handler");

    try {
        Meta::init();
        new OutputManager();

        if(Meta::isDebug()) header("Cache-Control: no-cache, no-store, must-revalidate");

        Meta::execute(ltrim($_GET["__path"] ?? "", "/"));

        $sess = Session::getInstanceOrNull();
        if($sess !== null) $sess->finalize();
        OutputManager::$root->output();
//        if(isset($_SESSION)) var_dump($_SESSION);
    } catch(\Throwable $ex) {
        Lang::handleError($ex);
    }

    function register_module(string $name, string $class, bool $debug = false) {
        global $MODULES;

        if($debug && !Meta::isDebug()) return;

        $MODULES[($debug ? (Meta::getSecret("meta.debugPrefix") . ".") : "") . strtolower($name)] = $class;
    }

    function error_handler(int $severity, string $error, string $filename, int $line) {
        throw new NativeError($error, 0, $severity, $filename, $line);
    }
}
