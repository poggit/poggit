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

namespace poggit\utils;

use poggit\Poggit;
use poggit\utils\internet\MysqlUtils;

class SessionUtils {
    private static $instance = null;

    public static function getInstance(): SessionUtils {
        if(self::$instance === null) self::$instance = new self;
        return self::$instance;
    }

    /**
     * @return SessionUtils|null
     */
    public static function getInstanceOrNull() {
        return self::$instance;
    }

    private $closed = false;

    private function __construct() {
        session_start();
//        session_write_close(); // TODO fix write lock problems
        if(!isset($_SESSION["poggit"]["anti_forge"])) $_SESSION["poggit"]["anti_forge"] = bin2hex(openssl_random_pseudo_bytes(64));

        Poggit::getLog()->i("Username = " . $this->getLogin()["name"]);
        
        // Online User Count
        $timeoutseconds = 300;
        $timestamp = time();
        $timeout = $timestamp - $timeoutseconds;

        $recorded = MysqlUtils::query("SELECT 1 FROM useronline WHERE ip = ?","s", Poggit::getClientIP());
        if (count($recorded) === 0) {
        $insertuser = MysqlUtils::query("INSERT INTO useronline VALUES (?, ?, ?) ", "iss", $timestamp, Poggit::getClientIP(), $_SERVER['PHP_SELF']);

        } else {
        MysqlUtils::query("UPDATE useronline SET timestamp = ? WHERE ip = ?", "is", $timestamp, Poggit::getClientIP());
        }
        $deleteusers = MysqlUtils::query("DELETE FROM useronline WHERE timestamp < ?", "i", $timeout);
        $result = MysqlUtils::query("SELECT DISTINCT ip FROM useronline WHERE file= ?", "s", $_SERVER['PHP_SELF']);
        $users = count($result);
        $GLOBALS["onlineusers"] = $users;
    }

    public function isLoggedIn(): bool {
        return isset($_SESSION["poggit"]["github"]);
    }

    public function setAntiForge(string $state) {
        if($this->closed) throw new \RuntimeException("Attempt to write session data after session write closed");
        $_SESSION["poggit"]["anti_forge"] = $state;
    }

    public function getAntiForge() {
        return $_SESSION["poggit"]["anti_forge"];
    }

    public function login(int $uid, string $name, string $accessToken, \stdClass $opts) {
        if($this->closed) throw new \RuntimeException("Attempt to write session data after session write closed");
        $_SESSION["poggit"]["github"] = [
            "uid" => $uid,
            "name" => $name,
            "access_token" => $accessToken,
            "opts" => $opts
        ];
        $this->hideTos();
    }

    /**
     * @return array|null
     */
    public function getLogin() {
        if(!$this->isLoggedIn()) return null;
        return $_SESSION["poggit"]["github"];
    }

    public function getAccessToken($default = "") {
        return $this->isLoggedIn() ? $_SESSION["poggit"]["github"]["access_token"] : $default;
    }

    public function createCsrf(): string {
        $rand = bin2hex(openssl_random_pseudo_bytes(16));
        if($this->closed) throw new \RuntimeException("Attempt to write session data after session write closed");
        $_SESSION["poggit"]["csrf"][$rand] = [microtime(true)];
        return $rand;
    }

    public function validateCsrf(string $token): bool {
        foreach(($_SESSION["poggit"]["csrf"] ?? []) as $tk => list($t)) {
            if(microtime(true) - $t > 10) {
                if($this->closed) throw new \RuntimeException("Attempt to write session data after session write closed");
                unset($_SESSION["poggit"]["csrf"][$tk]);
            }
        }
        if(isset($_SESSION["poggit"]["csrf"][$token])) return true;
        return false;
    }

    public function persistLoginLoc(string $loc) {
        if($this->closed) throw new \RuntimeException("Attempt to write session data after session write closed");
        $_SESSION["poggit"]["loginLoc"] = $loc;
    }

    public function removeLoginLoc(): string {
        if(!isset($_SESSION["poggit"]["loginLoc"])) return "";
        $loc = $_SESSION["poggit"]["loginLoc"];
        if($this->closed) throw new \RuntimeException("Attempt to write session data after session write closed");
        unset($_SESSION["poggit"]["loginLoc"]);
        return $loc;
    }

    public function hideTos() {
        if($this->closed) throw new \RuntimeException("Attempt to write session data after session write closed");
        return $_SESSION["poggit"]["hideTos"] = microtime(true);
    }

    public function tosHidden(): bool {
        return $_SESSION["poggit"]["hideTos"] ?? false;
    }

    public function resetPoggitSession() {
        if($this->closed) throw new \RuntimeException("Attempt to write session data after session write closed");
        $_SESSION["poggit"] = [];
    }

    public function finalize() {
    }

    public function close() {
        session_write_close();
        $this->closed = true;
    }
}
