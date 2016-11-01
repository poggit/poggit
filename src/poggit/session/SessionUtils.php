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

namespace poggit\session;

class SessionUtils {
    private static $instance = null;

    public static function getInstance() : SessionUtils {
        if(self::$instance === null) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    /**
     * @return SessionUtils|null
     */
    public static function getInstanceOrNull() {
        return self::$instance;
    }

    private function __construct() {
        session_start();
        session_write_close();
        if(!isset($_SESSION["poggit"]["anti_forge"])) {
            $_SESSION["poggit"]["anti_forge"] = bin2hex(openssl_random_pseudo_bytes(64));
        }
    }

    public function isLoggedIn() : bool {
        return isset($_SESSION["poggit"]["github"]);
    }

    public function setAntiForge(string $state) {
        $_SESSION["poggit"]["anti_forge"] = $state;
    }

    public function getAntiForge() {
        return $_SESSION["poggit"]["anti_forge"];
    }

    public function login(int $uid, string $name, string $accessToken, \stdClass $opts) {
        $_SESSION["poggit"]["github"] = [
            "uid" => $uid,
            "name" => $name,
            "access_token" => $accessToken,
            "opts" => $opts
        ];
    }

    /**
     * @return array|null
     */
    public function getLogin() {
        if(!$this->isLoggedIn()) {
            return null;
        }
        return $_SESSION["poggit"]["github"];
    }

    public function getAccessToken($default = "") {
        return $this->isLoggedIn() ? $_SESSION["poggit"]["github"]["access_token"] : $default;
    }

    public function createCsrf() : string {
        $rand = bin2hex(openssl_random_pseudo_bytes(16));
        $_SESSION["poggit"]["csrf"][$rand] = [microtime(true)];
        return $rand;
    }

    public function validateCsrf(string $token) : bool {
        if(isset($_SESSION["poggit"]["csrf"][$token])) {
            list($t) = $_SESSION["poggit"]["csrf"][$token];
            if(microtime(true) - $t < 10) {
                unset($_SESSION["poggit"]["csrf"][$token]);
                return true;
            }
        }
        return false;
    }

    public function persistLoginLoc(string $loc) {
        $_SESSION["poggit"]["loginLoc"] = $loc;
    }

    public function removeLoginLoc() : string {
        if(!isset($_SESSION["poggit"]["loginLoc"])) {
            return "";
        }
        $loc = $_SESSION["poggit"]["loginLoc"];
        unset($_SESSION["poggit"]["loginLoc"]);
        return $loc;
    }

    public function hideTos() {
        return $_SESSION["poggit"]["hideTos"] = microtime(true);
    }

    public function tosHidden() : bool {
        return $_SESSION["poggit"]["hideTos"] ?? false;
    }

    public function resetPoggitSession() {
        $_SESSION["poggit"] = [];
    }

    public function finalize() {
        $data = $_SESSION;
        session_start();
        $_SESSION = $data;
    }
}
