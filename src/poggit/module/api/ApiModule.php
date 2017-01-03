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

namespace poggit\module\api;

use poggit\module\api\lists\ListUserProjectsApi;
use poggit\module\api\rel\GetReleaseApi;
use poggit\module\Module;
use poggit\Poggit;
use poggit\utils\OutputManager;
use poggit\utils\SessionUtils;

class ApiModule extends Module {
    static $HANDLERS = [
        "projects.user" => ListUserProjectsApi::class,
        "releases.get" => GetReleaseApi::class
    ];

    public static $token = "";
    public static $warnings;

    public function getName(): string {
        return "api";
    }

    public function output() {
        self::$warnings = [];
        OutputManager::$plainTextOutput = true;
        header("Content-Type: application/json");
        try {
            $result = $this->output0();
        } catch(ApiException $e) {
            http_response_code($code = $e->getCode() === 0 ? 400 : $e->getCode());
            $result = ["apiError" => $e->getMessage()];
        }
        $result["httpCode"] = http_response_code();
        if(count(self::$warnings) > 0) $result["warnings"] = self::$warnings;
        echo preg_replace_callback('/^ +/m', function ($m) {
            return str_repeat(' ', strlen($m[0]) / 2);
        }, json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    public function output0() {
        $json = Poggit::getInput();
        $request = json_decode($json);
        if(!is_object($request)) throw new ApiException("Invalid JSON string: " . json_last_error_msg());
        if(!isset($request->request)) throw new ApiException("Invalid request: missing field 'request'");
        $headers = apache_request_headers();
        if(isset($headers["Authorization"])) self::$token = end(explode(" ", $headers["Authorization"]));
        if(self::$token === "" and isset($_REQUEST["access_token"])) self::$token = $_REQUEST["access_token"];
        if(self::$token === "" and isset($_COOKIE[session_name()])) {
            $session = SessionUtils::getInstance();
            if($session->validateCsrf($_GET["csrf"] ?? $request->csrf ?? "")) {
                self::$token = $session->getAccessToken("");
            } else {
                self::$warnings[] = "No login - CSRF token not provided";
            }
        }


        if(!isset(self::$HANDLERS[$request->request])) throw new ApiException("Request method $request->request not found", 404);
        $class = self::$HANDLERS[$request->request];
        /** @var ApiHandler $handler */
        $handler = new $class;
        assert($handler instanceof ApiHandler);
        return $handler->process($request);
    }
}
