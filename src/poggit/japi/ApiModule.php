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

namespace poggit\japi;

use poggit\account\Session;
use poggit\japi\lists\ListUserProjectsApi;
use poggit\japi\rel\GetReleaseApi;
use poggit\japi\rel\GetUserReleaseApi;
use poggit\Meta;
use poggit\module\Module;
use poggit\utils\OutputManager;
use stdClass;

class ApiModule extends Module {
    public static $HANDLERS = [
        "projects.user" => ListUserProjectsApi::class,
        "releases.get" => GetReleaseApi::class,
        "releases.user" => GetUserReleaseApi::class
    ];

    public static $token = "";
    public static $warnings;

    public function getName(): string {
        return "api";
    }

    public function output(): void {
        ApiModule::$warnings = [];
        OutputManager::$plainTextOutput = true;
        header("Content-Type: application/json");
        $result = new stdClass();
        try {
            $result = (object) $this->output0();
        } catch(ApiException $e) {
            http_response_code($code = $e->getCode() === 0 ? 400 : $e->getCode());
            $result->apiError = $e->getMessage();
        }
        $result->httpCode = http_response_code();
        if(count(ApiModule::$warnings) > 0) $result->warnings = ApiModule::$warnings;
        echo preg_replace_callback('/^ +/m', function($m) {
            return str_repeat(' ', strlen($m[0]) / 2);
        }, json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    public function output0() {
        $json = Meta::getInput();
        $request = json_decode($json);
        if(!is_object($request)) throw new ApiException("Invalid JSON string: " . json_last_error_msg());
        if(!isset($request->request)) throw new ApiException("Invalid request: missing field 'request'");
        $headers = apache_request_headers();
        if(isset($headers["Authorization"])) {
            $authArr = explode(" ", $headers["Authorization"]);
            ApiModule::$token = end($authArr);
        }
        if(ApiModule::$token === "" and isset($_REQUEST["access_token"])) ApiModule::$token = $_REQUEST["access_token"];
        if(ApiModule::$token === "" and isset($_COOKIE[session_name()])) {
            $session = Session::getInstance(false);
            if($session->validateCsrf($_GET["csrf"] ?? $request->csrf ?? "")) {
                ApiModule::$token = $session->getAccessToken("");
            } else {
                ApiModule::$warnings[] = "No login - CSRF token not provided";
            }
        }

        if(!isset(ApiModule::$HANDLERS[$request->request])) throw new ApiException("Request method $request->request not found", 404);
        $class = ApiModule::$HANDLERS[$request->request];
        /** @var ApiHandler $handler */
        $handler = new $class;
        assert($handler instanceof ApiHandler);
        return $handler->process($request);
    }
}
