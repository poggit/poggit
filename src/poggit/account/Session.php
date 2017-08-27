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

namespace poggit\account;

use poggit\Meta;
use poggit\utils\internet\Mysql;
use poggit\utils\OutputManager;

class Session {
    private static $instance = null;

    public static function getInstance(bool $online = true): Session {
        if(self::$instance === null) self::$instance = new self($online);
        return self::$instance;
    }

    /**
     * @return Session|null
     */
    public static function getInstanceOrNull() {
        return self::$instance;
    }

    private $closed = false;

    private function __construct(bool $online) {
        session_start();
//        session_write_close(); // TODO fix write lock problems
        if(!isset($_SESSION["poggit"]["anti_forge"])) $_SESSION["poggit"]["anti_forge"] = bin2hex(openssl_random_pseudo_bytes(64));

        Meta::getLog()->i("Username = " . $this->getName());
        if($this->isLoggedIn() && $online) {
            $bans = Meta::getSecret("perms.bans", true) ?? [];
            if(isset($bans[$uid = $this->getUid(-1)])) {
                OutputManager::terminateAll();
                http_response_code(403);
                header("Content-Type: text/plain");
                echo "Your account's access to Poggit has been blocked due to the following reason:\n{$bans[$uid]}\nShall you have any enquiries, find us on Gitter: https://gitter.im/poggit/Lobby";
                exit;
            }
            Mysql::query("INSERT INTO user_ips (uid, ip) VALUES (?, ?) ON DUPLICATE KEY UPDATE time = CURRENT_TIMESTAMP", "is", $this->getUid(), Meta::getClientIP());
        }

        if($online) {
            $timeoutseconds = 300;
            $timestamp = microtime(true);
            $timeout = $timestamp - $timeoutseconds;

            $recorded = Mysql::query("SELECT 1 FROM useronline WHERE ip = ?", "s", Meta::getClientIP());
            if(count($recorded) === 0) {
                Mysql::query("INSERT INTO useronline VALUES (?, ?, ?) ", "dss", $timestamp, Meta::getClientIP(), Meta::getModuleName());
            } else {
                Mysql::query("UPDATE useronline SET timestamp = ?, file = ? WHERE ip = ?", "dss", $timestamp, Meta::getModuleName(), Meta::getClientIP());
            }
            Mysql::query("DELETE FROM useronline WHERE timestamp < ?", "d", $timeout);
            Meta::$onlineUsers = Mysql::query("SELECT COUNT(DISTINCT ip) AS cnt FROM useronline")[0]["cnt"];
        }

//        foreach($_SESSION["poggit"]["submitFormToken"] ?? [] as $k => $v) {
//            if(time() - $v["time"] > 11100) {
//                unset($_SESSION["poggit"]["submitFormToken"][$k]);
//            }
//        }
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
    public function &getLogin() {
        $null = null;
        if(!$this->isLoggedIn()) return $null;
        return $_SESSION["poggit"]["github"];
    }

    public function getUid($default = 0) {
        return $this->isLoggedIn() ? $_SESSION["poggit"]["github"]["uid"] : $default;
    }

    public function getName($default = "") {
        return $this->isLoggedIn() ? $_SESSION["poggit"]["github"]["name"] : $default;
    }

    public function getAccessToken($default = "") {
        return $this->isLoggedIn() ? $_SESSION["poggit"]["github"]["access_token"] :
            ($default === true ? Meta::getDefaultToken() : $default);
    }

    public function getOpts() {
        return $this->isLoggedIn() ? $_SESSION["poggit"]["github"]["opts"] : null;
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

    public function createSubmitFormToken($data): string {
        if($this->closed) throw new \RuntimeException("Attempt to write session data after session write closed");
        $data["time"] = time();
        $submitFormToken = bin2hex(random_bytes(16));
        $_SESSION["poggit"]["submitFormToken"][$submitFormToken] = $data;
        return $submitFormToken;
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
