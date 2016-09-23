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
    if(!defined('POGGIT_INSTALL_PATH')) {
        define('POGGIT_INSTALL_PATH', realpath(__DIR__) . DIRECTORY_SEPARATOR);
    }
}

namespace poggit {

    use Firebase\JWT\JWT;
    use mysqli;
    use poggit\log\Log;
    use poggit\output\OutputManager;
    use poggit\page\error\InternalErrorPage;
    use poggit\page\error\NotFoundPage;
    use poggit\page\home\HomePage;
    use poggit\page\Page;
    use poggit\page\res\ResPage;
    use poggit\page\webhooks\GitHubAppWebhook;
    use poggit\page\webhooks\GitHubWebhook;
    use RuntimeException;

    if(!defined('poggit\INSTALL_PATH')) define('poggit\INSTALL_PATH', POGGIT_INSTALL_PATH);
    if(!defined('poggit\SOURCE_PATH')) define('poggit\SOURCE_PATH', INSTALL_PATH . "src" . DIRECTORY_SEPARATOR);
    if(!defined('poggit\SECRET_PATH')) define('poggit\SECRET_PATH', INSTALL_PATH . "secret" . DIRECTORY_SEPARATOR);
    if(!defined('poggit\RES_DIR')) define('poggit\RES_DIR', INSTALL_PATH . "res" . DIRECTORY_SEPARATOR);
    if(!defined('poggit\LOG_DIR')) define('poggit\LOG_DIR', INSTALL_PATH . "logs" . DIRECTORY_SEPARATOR);
    if(!defined('poggit\EARLY_ACCEPT')) define('poggit\EARLY_ACCEPT', "Accept: application/vnd.github.machine-man-preview+json");

    $MODULES = [];
    try {
        set_error_handler(__NAMESPACE__ . "\\error_handler");
        spl_autoload_register(function (string $class) {
            $base = SOURCE_PATH . str_replace("\\", DIRECTORY_SEPARATOR, $class);
            $extensions = [".php" . PHP_MAJOR_VERSION . PHP_MINOR_VERSION, ".php" . PHP_MAJOR_VERSION, ".php"];
            foreach($extensions as $ext) {
                $file = $base . $ext;
                if(is_file($file)) {
                    require_once $file;
                    return;
                }
            }
        });
        $outputManager = new OutputManager();
        $log = new Log();

        registerModule(HomePage::class);
        registerModule(ResPage::class);
        registerModule(GitHubWebhook::class);
        registerModule(GitHubAppWebhook::class);

        $requestPath = $_GET["__path"] ?? "/";
        $input = file_get_contents("php://input");

        $log->i($_SERVER["REMOTE_ADDR"] . " " . $requestPath);
        $log->v($requestPath . " " . json_encode($input));
        $startEvalTime = microtime(true);

        $paths = array_filter(explode("/", $requestPath, 2));
        if(count($paths) === 0) {
            $paths[] = "home";
        }
        if(count($paths) === 1) {
            $paths[] = "";
        }
        list($module, $query) = $paths;
        if(isset($MODULES[$module])) {
            $class = $MODULES[$module];
            $page = new $class($query);
        } else {
            $page = new NotFoundPage($query);
        }

        $page->output();
        $endEvalTime = microtime(true);
        $log->v("Safely completed: " . ((int) (($endEvalTime - $startEvalTime) * 1000)) . "ms");
        header("X-Execution-Time: " . ($endEvalTime - $startEvalTime));
        $outputManager->output();
    } catch(\Throwable $e) {
        error_handler(E_ERROR, get_class($e) . ": " . $e->getMessage() . " " .
            json_encode($e->getTrace()), $e->getFile(), $e->getLine());
    }

    function registerModule(string $class) {
        global $MODULES;

        if(!(class_exists($class) and is_subclass_of($class, Page::class))) {
            throw new RuntimeException("Want Class<? extends Page>, got Class<$class>");
        }

        /** @var Page $instance */
        $instance = new $class("");
        $MODULES[$instance->getName()] = $class;
    }

    function getLog() : Log {
        global $log;
        return $log;
    }

    function getInput() : string {
        global $input;
        return $input;
    }

    function headIncludes() {
        ?>
        <script src="//code.jquery.com/jquery-1.12.4.min.js"></script>
        <script src="<?= getRootPath() ?>res/std.js"></script>
        <link type="text/css" rel="stylesheet" href="<?= getRootPath() ?>res/style.css">
        <?php
    }

    /**
     * Redirect user to a path under the Poggit root, without a leading slash
     *
     * @param string $target default homepage
     */
    function redirect(string $target = "") {
        header("Location: " . getRootPath() . $target);
        die;
    }

    function getDb() : mysqli {
        global $db;
        if(isset($db)) {
            return $db;
        }
        $data = getSecret("mysql");
        try {
            /** @noinspection PhpUsageOfSilenceOperatorInspection */
            $db = @new mysqli($data["host"], $data["user"], $data["password"], $data["schema"], $data["port"] ?? 3306);
        } catch(\Exception $e) {
            getLog()->e("mysqli error: " . $e->getMessage());
        }
        if($db->connect_error) {
            getLog()->e("mysqli error: $db->connect_error");
            die;
        }
        return $db;
    }

    function getSecret(string $name) {
        global $secretsCache;
        if(!isset($secretsCache)) {
            $secretsCache = json_decode(file_get_contents(SECRET_PATH . "secrets.json"), true);
        }
        $secrets = $secretsCache;
        if(isset($secrets[$name])) {
            return $secrets[$name];
        }
        $parts = explode(".", $name);
        foreach($parts as $part) {
            if(!is_array($secrets) or !isset($secrets[$part])) {
                throw new RuntimeException("Unknown secret $part");
            }
            $secrets = $secrets[$part];
        }
        if(count($parts) > 1) {
            $secretsCache[$name] = $secrets;
        }
        return $secrets;
    }

    function getRootPath() : string {
        return "/" . trim(getSecret("paths.url"), "/") . "/";
    }

    function error_handler(int $errno, string $error, string $errfile, int $errline) {
        global $log, $outputManager;
        http_response_code(500);
        if(!isset($log)) {
            $log = new Log();
        }
        $refid = mt_rand();
        $log->e("Error#$refid Level $errno error: $error at $errfile:$errline");
        if($outputManager !== null) {
            $outputManager->terminate();
        }
        (new InternalErrorPage((string) $refid))->output();
    }

    function curlPost(string $url, $postFields, string ...$extraHeaders) {
        $headers = ["User-Agent: Poggit/1.0", "Accept: application/json"];
        foreach($extraHeaders as $header) {
            if(strpos($header, "Accept: ") === 0) {
                $headers[1] = $header;
            } else {
                $headers[] = $header;
            }
        }
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $ret = curl_exec($ch);
        curl_close($ch);
        return $ret;
    }

    function curlGet(string $url, string ...$extraHeaders) {
        $headers = ["User-Agent: Poggit/1.0", "Accept: application/json"];
        foreach($extraHeaders as $header) {
            if(strpos($header, "Accept: ") === 0) {
                $headers[1] = $header;
            } else {
                $headers[] = $header;
            }
        }
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $ret = curl_exec($ch);
        curl_close($ch);
        return $ret;
    }

    function ghApiPost(string $url, $postFields, string $token = "", bool $customAccept = false) {
        $headers = [];
        if($customAccept) {
            $headers[] = EARLY_ACCEPT;
        }
        if($token) {
            $headers[] = "Authorization: bearer $token";
        }
        $data = curlPost($url, $postFields, ...$headers);
        if(is_string($data)) {
            $data = json_decode($data);
            if(is_object($data)) {
                if(!isset($data->message, $data->documentation_url)) {
                    return $data;
                }
                throw new RuntimeException("GitHub API error: $data->message");
            } elseif(is_array($data)) {
                return $data;
            }
        }
        throw new RuntimeException("Failed to access data from GitHub API");
    }

    function ghApiGet(string $url, string $token = "", bool $customAccept = false) {
        $headers = [];
        if($customAccept) {
            $headers[] = EARLY_ACCEPT;
        }
        if($token) {
            $headers[] = "Authorization: bearer $token";
        }
        $data = curlGet($url, ...$headers);
        if(is_string($data)) {
            $data = json_decode($data);
            if(is_object($data)) {
                if(!isset($data->message, $data->documentation_url)) {
                    return $data;
                }
                throw new RuntimeException("GitHub API error: $data->message");
            } elseif(is_array($data)) {
                return $data;
            }
        }
        throw new RuntimeException("Failed to access data from GitHub API");
    }

    /**
     * @return string
     * @deprecated
     */
    function getJWT() : string {
        global $jwt;
        if(isset($jwt)) {
            return $jwt;
        }
        $token = [
            "iss" => getSecret("integration.id"),
            "iat" => time(),
            "exp" => time() + 60
        ];
        $key = file_get_contents(SECRET_PATH . "poggit.pem");
        return $jwt = JWT::encode($token, $key, "RS256");
    }

    /**
     * @param string $userId
     * @param int    $installId
     * @return string
     * @deprecated
     */
    function getIntegrationToken(string $userId, int $installId) : string {
        $key = "poggit.ghin.token.$userId";
        if(apcu_exists($key)) {
            $data = json_decode(apcu_fetch($key));
            if($data->expiry > time()) {
                return $data->token;
            }
        }
        $response = ghApiPost("https://api.github.com/installations/$installId/access_tokens",
            json_encode(["user_id" => $userId]), getJWT(), EARLY_ACCEPT);
        $token = $response->token;
        $expiry = $response->expires_at;
        $data = new \stdClass();
        $data->token = $token;
        $data->expiry = $expiry;
        apcu_store($key, json_encode($data));
        return $token;
    }
}
