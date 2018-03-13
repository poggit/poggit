<?php

/*
 * Poggit
 *
 * Copyright (C) 2016-2018 Poggit
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
use poggit\utils\OutputManager;
use RuntimeException;
use stdClass;
use function bin2hex;
use function header;
use function http_response_code;
use function microtime;
use function random_bytes;
use function session_name;
use function session_set_cookie_params;
use function session_start;
use function session_write_close;
use function setcookie;
use function time;

class Session {
    public static $CHECK_AUTO_LOGIN = true;

    private static $instance = null;

    public static function getInstance(): Session {
        if(self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    /**
     * @return Session|null
     */
    public static function getInstanceOrNull() {
        return self::$instance;
    }

    private $closed = false;

    private function __construct() {
        session_name("PoggitSess");
        session_set_cookie_params(3600, Meta::root());
        session_start([
            "gc_maxlifetime" => 3600,
            ""
        ]);
//        session_write_close(); // TODO fix write lock problems
        if(!isset($_SESSION["poggit"]["anti_forge"])) $_SESSION["poggit"]["anti_forge"] = bin2hex(random_bytes(64));

        if(self::$CHECK_AUTO_LOGIN && !$this->isLoggedIn() && ($_COOKIE["autoLogin"] ?? "0") === "1") {
            $this->persistLoginLoc(Meta::getSecret("meta.extPath") . Meta::getRequestPath());
            $clientId = Meta::getSecret("app.clientId");
            $antiForge = $_SESSION["poggit"]["anti_forge"];
            Meta::redirect("https://github.com/login/oauth/authorize?client_id={$clientId}&state={$antiForge}&scope=user:email," . ($_COOKIE["ghScopes"] ?? "repo,read:orgs"), true);
        }

        Meta::getLog()->v("Username = " . $this->getName());
        if($this->isLoggedIn()) {
            $bans = Meta::getSecret("perms.bans", true) ?? [];
            if(isset($bans[$uid = $this->getUid(-1)])) {
                Meta::getLog()->v("Banned");
                OutputManager::terminateAll();
                http_response_code(403);
                header("Content-Type: text/plain");
                echo "Your account's access to Poggit has been blocked due to the following reason:\n{$bans[$uid]}\nShall you have any enquiries, find us on Discord: " . Meta::getSecret("discord.serverInvite");
                exit;
            }
        }

        $ipBans = Meta::getSecret("perms.ipBans", true) ?? [];
        if(isset($ipBans[$ip = Meta::getClientIP()])) {
            Meta::getLog()->v("IP-Banned");
            OutputManager::terminateAll();
            http_response_code(403);
            header("Content-Type: text/plain");
            echo "Your account's access to Poggit has been blocked due to the following reason:\n{$ipBans[$ip]}\nShall you have any enquiries, find us on Discord: " . Meta::getSecret("discord.serverInvite");
            exit;
        }

        foreach($_SESSION["poggit"]["submitFormToken"] ?? [] as $k => $v) {
            if(time() - $v["time"] > 86400) {
                unset($_SESSION["poggit"]["submitFormToken"][$k]);
            }
        }
    }

    public function isLoggedIn(): bool {
        return isset($_SESSION["poggit"]["github"]);
    }

    public function setAntiForge(string $state) {
        if($this->closed) throw new RuntimeException("Attempt to write session data after session write closed");
        $_SESSION["poggit"]["anti_forge"] = $state;
    }

    public function getAntiForge() {
        return $_SESSION["poggit"]["anti_forge"];
    }

    public function login(int $uid, string $name, string $accessToken, array $scopesArray, int $lastLogin, int $lastNotif, stdClass $opts) {
        if($this->closed) throw new RuntimeException("Attempt to write session data after session write closed");
        $_SESSION["poggit"]["github"] = [
            "uid" => $uid,
            "name" => $name,
            "access_token" => $accessToken,
            "scopes" => $scopesArray,
            "last_login" => $lastLogin,
            "this_login" => time(),
            "last_notif" => $lastNotif,
            "opts" => $opts
        ];
        $this->hideTos();
        if($opts->autoLogin ?? true) {
            self::setCookie("autoLogin", "1");
        }
    }

    /**
     * @return array|null
     */
    public function &getLogin() {
        $null = null;
        if(!$this->isLoggedIn()) return $null;
        return $_SESSION["poggit"]["github"];
    }

    /** @noinspection ReturnTypeCanBeDeclaredInspection
     * @param int $default
     * @return int|mixed
     */
    public function getUid($default = 0) {
        return $this->isLoggedIn() ? $_SESSION["poggit"]["github"]["uid"] : $default;
    }

    /** @noinspection ReturnTypeCanBeDeclaredInspection
     * @param string $default
     * @return string|mixed
     */
    public function getName($default = "") {
        return $this->isLoggedIn() ? $_SESSION["poggit"]["github"]["name"] : $default;
    }

    /** @noinspection ReturnTypeCanBeDeclaredInspection
     * @param string $default
     * @return string|mixed
     */
    public function getAccessToken($default = "") {
        return $this->isLoggedIn() ? $_SESSION["poggit"]["github"]["access_token"] :
            ($default === true ? Meta::getDefaultToken() : $default);
    }

    public function getLastNotif($default = 0): int {
        return $this->isLoggedIn() ? ($_SESSION["poggit"]["github"]["last_notif"] ?? time()) : $default;
    }

    /**
     * @return stdClass|null
     */
    public function getOpts() {
        return $this->isLoggedIn() ? $_SESSION["poggit"]["github"]["opts"] : null;
    }

    public function createCsrf(): string {
        $rand = bin2hex(random_bytes(16));
        if($this->closed) throw new RuntimeException("Attempt to write session data after session write closed");
        $_SESSION["poggit"]["csrf"][$rand] = [microtime(true)];
        return $rand;
    }

    public function validateCsrf(string $token): bool {
        foreach($_SESSION["poggit"]["csrf"] ?? [] as $tk => list($t)) {
            if(microtime(true) - $t > 10) {
                if($this->closed) throw new RuntimeException("Attempt to write session data after session write closed");
                unset($_SESSION["poggit"]["csrf"][$tk]);
            }
        }
        if(isset($_SESSION["poggit"]["csrf"][$token])) return true;
        return false;
    }

    public function persistLoginLoc(string $loc) {
        if($this->closed) throw new RuntimeException("Attempt to write session data after session write closed");
        $_SESSION["poggit"]["loginLoc"] = $loc;
    }

    public function removeLoginLoc(): string {
        if(!isset($_SESSION["poggit"]["loginLoc"])) return "";
        $loc = $_SESSION["poggit"]["loginLoc"];
        if($this->closed) throw new RuntimeException("Attempt to write session data after session write closed");
        unset($_SESSION["poggit"]["loginLoc"]);
        return $loc;
    }

    public function createSubmitFormToken($data): string {
        if($this->closed) throw new RuntimeException("Attempt to write session data after session write closed");
        $data["time"] = time();
        $submitFormToken = bin2hex(random_bytes(16));
        $_SESSION["poggit"]["submitFormToken"][$submitFormToken] = $data;
        return $submitFormToken;
    }

    public function hideTos() {
        if($this->closed) throw new RuntimeException("Attempt to write session data after session write closed");
        return $_SESSION["poggit"]["hideTos"] = microtime(true);
    }

    public function tosHidden(): bool {
        return $_SESSION["poggit"]["hideTos"] ?? false;
    }

    public function resetPoggitSession() {
        if($this->closed) throw new RuntimeException("Attempt to write session data after session write closed");
        $_SESSION["poggit"] = [];
        self::setCookie("autoLogin", "0");
    }

    public function finalize() {
    }

    public function close() {
        session_write_close();
        $this->closed = true;
    }

    public function showsIcons(): bool {
        if(isset($_REQUEST["showIcons"])) {
            return $_REQUEST["showIcons"] !== "off";
        }

        return $this->getOpts()->showIcons ?? true;
    }

    public static function setCookie(string $name, string $value): void {
        setcookie($name, $value, time() + 315360000, Meta::root());
    }
}
