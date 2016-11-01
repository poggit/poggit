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

namespace {
    if(!defined('POGGIT_INSTALL_PATH')) define('POGGIT_INSTALL_PATH', realpath(__DIR__) . DIRECTORY_SEPARATOR);
}

namespace poggit {

    use poggit\exception\AltModuleException;
    use poggit\module\error\InternalErrorPage;
    use poggit\module\error\NotFoundPage;
    use poggit\module\Module;
    use poggit\output\OutputManager;
    use poggit\session\SessionUtils;
    use RuntimeException;

    const DO_TIMINGS = false;
    if(!defined('poggit\INSTALL_PATH')) define('poggit\INSTALL_PATH', POGGIT_INSTALL_PATH);
    if(!defined('poggit\SOURCE_PATH')) define('poggit\SOURCE_PATH', INSTALL_PATH . "src" . DIRECTORY_SEPARATOR);
    if(!defined('poggit\LIBS_PATH')) define('poggit\LIBS_PATH', INSTALL_PATH . "libs" . DIRECTORY_SEPARATOR);
    if(!defined('poggit\SECRET_PATH')) define('poggit\SECRET_PATH', INSTALL_PATH . "secret" . DIRECTORY_SEPARATOR);
    if(!defined('poggit\RES_DIR')) define('poggit\RES_DIR', INSTALL_PATH . "res" . DIRECTORY_SEPARATOR);
    if(!defined('poggit\RESOURCE_DIR')) define('poggit\RESOURCE_DIR', INSTALL_PATH . "resources" . DIRECTORY_SEPARATOR);
    if(!defined('poggit\JS_DIR')) define('poggit\JS_DIR', INSTALL_PATH . "js" . DIRECTORY_SEPARATOR);
    if(!defined('poggit\LOG_DIR')) define('poggit\LOG_DIR', INSTALL_PATH . "logs" . DIRECTORY_SEPARATOR);

    $MODULES = [];
    try {
        spl_autoload_register(function (string $class) {
            $bases = [SOURCE_PATH . str_replace("\\", DIRECTORY_SEPARATOR, $class)];
//            foreach(new \DirectoryIterator(LIBS_PATH) as $dir) {
//                if(realpath(dirname($dir)) === realpath(LIBS_PATH) and is_dir($d = LIBS_PATH . $dir . "/src/")) $bases[] = $d;
//            }
            $extensions = [".php" . PHP_MAJOR_VERSION . PHP_MINOR_VERSION, ".php" . PHP_MAJOR_VERSION, ".php"];
            foreach($extensions as $ext) {
                foreach($bases as $base) {
                    $file = $base . $ext;
                    if(is_file($file)) {
                        require_once $file;
                        return;
                    }
                }
            }
        });
        set_error_handler(__NAMESPACE__ . "\\error_handler");
        Poggit::checkDeps();
        $outputManager = new OutputManager();
        $log = new Log();

        include_once SOURCE_PATH . "modules.php";

        $requestPath = $_GET["__path"] ?? "/";
        $input = file_get_contents("php://input");

        $log->i($_SERVER["REMOTE_ADDR"] . " " . $requestPath);
        $log->v($requestPath . " " . json_encode($input, JSON_UNESCAPED_SLASHES));
        $timings = [];
        $startEvalTime = microtime(true);

        $paths = array_filter(explode("/", $requestPath, 2));
        if(count($paths) === 0) $paths[] = "home";
        if(count($paths) === 1) $paths[] = "";
        list($moduleName, $query) = $paths;

        if(isset($MODULES[strtolower($moduleName)])) {
            $class = $MODULES[strtolower($moduleName)];
            $module = new $class($query);
        } else {
            $module = new NotFoundPage($requestPath);
        }

        try {
            retry:
            Module::$currentPage = $module;
            $module->output();
        } catch(AltModuleException $ex) {
            $module = $ex->getAltModule();
            goto retry;
        }

        $endEvalTime = microtime(true);
        if(DO_TIMINGS) {
            Poggit::getLog()->d("Timings: " . json_encode($timings));
        }
        $log->v("Safely completed: " . ((int) (($endEvalTime - $startEvalTime) * 1000)) . "ms");
        Poggit::showStatus();
        $sess = SessionUtils::getInstanceOrNull();
        if($sess !== null) $sess->finalize();
        $outputManager->output();
    } catch(\Throwable $e) {
        error_handler(E_ERROR, get_class($e) . ": " . $e->getMessage() . "\n" .
            $e->getTraceAsString(), $e->getFile(), $e->getLine());
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

    function getInput() : string {
        global $input;
        return $input;
    }

    function getRequestPath() : string {
        global $requestPath;
        return $requestPath;
    }

    /**
     * Redirect user to a path under the Poggit root, without a leading slash
     *
     * @param string $target   default homepage
     * @param bool   $absolute default false
     */
    function redirect(string $target = "", bool $absolute = false) {
        header("Location: " . ($absolute ? "" : Poggit::getRootPath()) . $target);
        Poggit::showStatus();
        die;
    }

    function error_handler(int $errno, string $error, string $errfile, int $errline) {
        global $log;
        http_response_code(500);
        $refid = mt_rand();
        if(Poggit::$plainTextOutput) {
            OutputManager::$current->outputTree();
            echo "Error#$refid Level $errno error at $errfile:$errline: $error\n";
        }
        if(!isset($log)) $log = new Log();
        $log->e("Error#$refid Level $errno error at $errfile:$errline: $error");
        if(!Poggit::$plainTextOutput) {
            OutputManager::terminateAll();
            (new InternalErrorPage((string) $refid))->output();
        }
        die;
    }

    function addTimings($event) {
        global $timings, $startEvalTime;
        $timings[] = [$event, microtime(true) - $startEvalTime];
    }
}
