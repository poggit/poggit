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

namespace poggit;

use poggit\module\AltModuleException;
use poggit\module\error\FoundPage;
use poggit\module\error\NotFoundPage;
use poggit\module\Module;
use poggit\utils\internet\CurlUtils;
use poggit\utils\lang\GlobalVarStream;
use poggit\utils\lang\LangUtils;
use poggit\utils\Log;
use poggit\utils\OutputManager;
use poggit\utils\SessionUtils;
use RuntimeException;

final class Poggit {
    const POGGIT_VERSION = "1.0-beta";
    const GUEST = 0;
    const MEMBER = 1;
    const CONTRIBUTOR = 2;
    const MODERATOR = 3;
    const REVIEWER = 4;
    const ADM = 5; 

    private static $ADMLV;
    public static $GIT_COMMIT;
    private static $log;
    private static $input;
    private static $requestId;
    private static $requestPath;
    private static $requestMethod;
    private static $moduleName;
    public static $onlineUsers;

    public static function init() {
        self::$ADMLV = json_decode(base64_decode("eyJTT0YzIjo1LCJBd3phdyI6NSwiZGt0YXBwcyI6NSwiVGh1bmRlcjMzMzQ1Ijo0LCJKYWNrTm9vcmRodWlzIjo0LCJyb2Jza2UxMTAiOjQsImJvcmVkcGhvdG9uIjo0fQ=="), true);

        if(file_exists(INSTALL_PATH . ".git/HEAD")){ //Found Git information!
            $ref = trim(file_get_contents(INSTALL_PATH . ".git/HEAD"));
            if(preg_match('/^[0-9a-f]{40}$/i', $ref)){
                self::$GIT_COMMIT = strtolower($ref);
            }elseif(substr($ref, 0, 5) === "ref: "){
                $refFile = INSTALL_PATH . ".git/" . substr($ref, 5);
                if(is_file($refFile)){
                    self::$GIT_COMMIT = strtolower(trim(file_get_contents($refFile)));
                }
            }
        }
        if(!isset(self::$GIT_COMMIT)){ //Unknown :(
            self::$GIT_COMMIT = str_repeat("00", 20);
        }

        if(isset($_SERVER["HTTP_CF_RAY"])) {
            Poggit::$requestId = substr(md5($_SERVER["HTTP_CF_RAY"]), 0, 4) . "-" . $_SERVER["HTTP_CF_RAY"];
        } else {
            Poggit::$requestId = bin2hex(random_bytes(8));
        }

        LangUtils::checkDeps();
        GlobalVarStream::register();
        Poggit::$log = new Log;
        Poggit::$input = file_get_contents("php://input");
    }

    public static function execute(string $path) {
        global $MODULES, $startEvalTime;

        include_once SOURCE_PATH . "modules.php";

        Poggit::getLog()->i(sprintf("%s: %s %s", Poggit::getClientIP(), Poggit::$requestMethod = $_SERVER["REQUEST_METHOD"], Poggit::$requestPath = $path));
        $timings = [];
        $startEvalTime = microtime(true);

        $paths = array_filter(explode("/", $path, 2));
        if(count($paths) === 0) $paths[] = "home";
        if(count($paths) === 1) $paths[] = "";
        list($moduleName, $query) = $paths;

        Poggit::$moduleName = strtolower($moduleName);

        if(isset($MODULES[strtolower($moduleName)])) {
            $class = $MODULES[strtolower($moduleName)];
            $module = new $class($query);
        } else {
            $module = new NotFoundPage($path);
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
        if(DO_TIMINGS) Poggit::getLog()->d("Timings: " . json_encode($timings));
        Poggit::getLog()->v("Safely completed: " . ((int) (($endEvalTime - $startEvalTime) * 1000)) . "ms");

        if(Poggit::isDebug()) Poggit::showStatus();
    }

    public static function getClientIP(): string {
        return $_SERVER["HTTP_CF_CONNECTING_IP"] ?? $_SERVER["HTTP_X_FORWARDED_FOR"] ?? $_SERVER["REMOTE_ADDR"];
    }

    public static function hasLog(): bool {
        return isset(Poggit::$log);
    }

    public static function getLog(): Log {
        return Poggit::$log;
    }
    
    public static function getInput(): string {
        return Poggit::$input;
    }

    public static function getRequestId(): string {
        return Poggit::$requestId;
    }

    public static function getRequestPath(): string {
        return Poggit::$requestPath;
    }

    public static function getRequestMethod(): string {
        return Poggit::$requestMethod;
    }

    public static function getModuleName(): string {
        return Poggit::$moduleName;
    }


    public static function getTmpFile($ext = ".tmp"): string {
        $tmpDir = rtrim(Poggit::getSecret("meta.tmpPath", true) ?? sys_get_temp_dir(), "/") . "/";
        $file = tempnam($tmpDir, $ext);
//        do {
//            $file = $tmpDir . bin2hex(random_bytes(4)) . $ext;
//        } while(is_file($file));
//        register_shutdown_function("unlink", $file);
        return $file;
    }

    public static function showStatus() {
        global $startEvalTime;
        header("X-Status-Execution-Time: " . sprintf("%f", (microtime(true) - $startEvalTime)));
        header("X-Status-cURL-Queries: " . CurlUtils::$curlCounter);
        header("X-Status-cURL-HostNotResolved: " . CurlUtils::$curlRetries);
        header("X-Status-cURL-Time: " . sprintf("%f", CurlUtils::$curlTime));
        header("X-Status-cURL-Size: " . CurlUtils::$curlBody);
        header("X-Status-MySQL-Queries: " . CurlUtils::$mysqlCounter);
        header("X-Status-MySQL-Time: " . sprintf("%f", CurlUtils::$mysqlTime));
        if(isset(CurlUtils::$ghRateRemain)) header("X-GitHub-RateLimit-Remaining: " . CurlUtils::$ghRateRemain);
    }

    public static function addTimings($event) {
        global $timings, $startEvalTime;
        $timings[] = [$event, microtime(true) - $startEvalTime];
    }

    public static function getSecret(string $name, bool $supressMissing = false) {
        global $secretsCache;
        if(!isset($secretsCache)) $secretsCache = json_decode($path = file_get_contents(SECRET_PATH . "secrets.json"), true);
        $secrets = $secretsCache;
        if(isset($secrets[$name])) return $secrets[$name];
        $parts = array_filter(explode(".", $name));
        foreach($parts as $part) {
            if(!is_array($secrets) or !isset($secrets[$part])) {
                if($supressMissing) return null; else throw new RuntimeException("Unknown secret $part");
            }
            $secrets = $secrets[$part];
        }
        if(count($parts) > 1) $secretsCache[$name] = $secrets;
        return $secrets;
    }

    /**
     * Returns the internally absolute path to Poggit site.
     *
     * Example return value: <code>/poggit/</code>
     *
     * @return string
     */
    public static function getRootPath(): string {
        // by splitting into two trim calls, only one slash will be returned for empty meta.intPath value
        return rtrim("/" . ltrim(Poggit::getSecret("meta.intPath"), "/"), "/") . "/";
    }

    public static function getCurlTimeout(): int {
        return Poggit::getSecret("meta.curl.timeout");
    }

    public static function getCurlPerPage(): int {
        return Poggit::getSecret("meta.curl.perPage");
    }

    public static function isDebug(): bool {
        return Poggit::getSecret("meta.debug");
    }

    public static function getAdmlv(string $user = null): int {
        return Poggit::$ADMLV[$user ?? SessionUtils::getInstance()->getName()] ?? 0;
    }

    /**
     * Redirect user to a path under the Poggit root, without a leading slash
     *
     * @param string $target   default homepage
     * @param bool   $absolute default false
     */
    public static function redirect(string $target = "", bool $absolute = false) {
        
        header("Location: " . ($target = ($absolute ? "" : Poggit::getRootPath()) . $target));
        http_response_code(302);
        if(Poggit::isDebug()) Poggit::showStatus();
        OutputManager::terminateAll();
        (new FoundPage($target))->output();
        die;
    }

    private function __construct() {
    }
}
