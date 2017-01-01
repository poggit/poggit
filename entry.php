<?php

/*
 * Copyright 2017 Poggit
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
}

namespace poggit {
    use poggit\module\Module;
    use poggit\utils\lang\LangUtils;
    use poggit\utils\lang\NativeError;
    use poggit\utils\OutputManager;
    use poggit\utils\SessionUtils;
    use RuntimeException;

    const DO_TIMINGS = false;
    require_once __DIR__ . "/src/paths.php";
    set_error_handler(__NAMESPACE__ . "\\error_handler");

    try {
        Poggit::init();
        new OutputManager();

        if(Poggit::isDebug()) header("Cache-Control: no-cache");

        Poggit::execute($_GET["__path"] ?? "/");

        $sess = SessionUtils::getInstanceOrNull();
        if($sess !== null) $sess->finalize();
        OutputManager::$root->output();
    } catch(\Throwable $ex) {
        LangUtils::handleError($ex);
    }

    function registerModule(string $class) {
        global $MODULES;

        if(!(class_exists($class) and is_subclass_of($class, Module::class))) {
            throw new RuntimeException("Want Class<? extends Page>, got Class<$class>");
        }

        /** @var Module $instance */
        $instance = new $class("");
        foreach($instance->getAllNames() as $name) {
            $MODULES[strtolower($name)] = $class;
        }
    }

    function error_handler(int $errno, string $error, string $errfile, int $errline) {
        throw new NativeError($error, 0, $errno, $errfile, $errline);
    }
}
