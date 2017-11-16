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

use poggit\account\Session;
use poggit\errdoc\FoundPage;
use poggit\errdoc\NotFoundPage;
use poggit\module\AltModuleException;
use poggit\module\Module;
use poggit\utils\internet\Curl;
use poggit\utils\internet\Mysql;
use poggit\utils\lang\Lang;
use poggit\utils\Log;
use poggit\utils\OutputManager;
use RuntimeException;

final class Meta {
    const POGGIT_VERSION = "1.0-beta";
    const ADMLV_GUEST = 0;
    const ADMLV_MEMBER = 1;
    const ADMLV_CONTRIBUTOR = 2;
    const ADMLV_MODERATOR = 3;
    const ADMLV_REVIEWER = 4;
    const ADMLV_ADMIN = 5;

    const ADMLV_MAP = [
        self::ADMLV_GUEST => "Guest",
        self::ADMLV_MEMBER => "Member",
        self::ADMLV_CONTRIBUTOR => "Contributor",
        self::ADMLV_MODERATOR => "Moderator",
        self::ADMLV_REVIEWER => "Reviewer",
        self::ADMLV_ADMIN => "Admin",
    ];

    const DOMAIN_MAPS = [
        ".Internal" => ["poggit.pmmp.io"],
        "Google" => ["com.google.android.googlequicksearchbox", "google.com", "google.ac", "google.ad", "google.ae", "google.com.af", "google.com.ag", "google.com.ai", "google.al", "google.am", "google.co.ao", "google.com.ar", "google.as", "google.at", "google.com.au", "google.az", "google.ba", "google.com.bd", "google.be", "google.bf", "google.bg", "google.com.bh", "google.bi", "google.bj", "google.com.bn", "google.com.bo", "google.com.br", "google.bs", "google.bt", "google.co.bw", "google.by", "google.com.bz", "google.ca", "google.com.kh", "google.cc", "google.cd", "google.cf", "google.cat", "google.cg", "google.ch", "google.ci", "google.co.ck", "google.cl", "google.cm", "google.cn", "google.com.co", "google.co.cr", "google.com.cu", "google.cv", "google.cx", "google.com.cy", "google.cz", "google.de", "google.dj", "google.dk", "google.dm", "google.com.do", "google.dz", "google.com.ec", "google.ee", "google.com.eg", "google.es", "google.com.et", "google.eu", "google.fi", "google.com.fj", "google.fm", "google.fr", "google.ga", "google.ge", "google.gf", "google.gg", "google.com.gh", "google.com.gi", "google.gl", "google.gm", "google.gp", "google.gr", "google.com.gt", "google.gy", "google.com.hk", "google.hn", "google.hr", "google.ht", "google.hu", "google.co.id", "google.iq", "google.ie", "google.co.il", "google.im", "google.co.in", "google.io", "google.is", "google.it", "google.je", "google.com.jm", "google.jo", "google.co.jp", "google.co.ke", "google.ki", "google.kg", "google.co.kr", "google.com.kw", "google.kz", "google.la", "google.com.lb", "google.com.lc", "google.li", "google.lk", "google.co.ls", "google.lt", "google.lu", "google.lv", "google.com.ly", "google.co.ma", "google.md", "google.me", "google.mg", "google.mk", "google.ml", "google.com.mm", "google.mn", "google.ms", "google.com.mt", "google.mu", "google.mv", "google.mw", "google.com.mx", "google.com.my", "google.co.mz", "google.com.na", "google.ne", "google.nf", "google.com.ng", "google.com.ni", "google.nl", "google.no", "google.com.np", "google.nr", "google.nu", "google.co.nz", "google.com.om", "google.com.pk", "google.com.pa", "google.com.pe", "google.com.ph", "google.pl", "google.com.pg", "google.pn", "google.com.pr", "google.ps", "google.pt", "google.com.py", "google.com.qa", "google.ro", "google.rs", "google.ru", "google.rw", "google.com.sa", "google.com.sb", "google.sc", "google.se", "google.com.sg", "google.sh", "google.si", "google.sk", "google.com.sl", "google.sn", "google.sm", "google.so", "google.st", "google.sr", "google.com.sv", "google.td", "google.tg", "google.co.th", "google.com.tj", "google.tk", "google.tl", "google.tm", "google.to", "google.tn", "google.com.tr", "google.tt", "google.com.tw", "google.co.tz", "google.com.ua", "google.co.ug", "google.co.uk", "google.us", "google.com.uy", "google.co.uz", "google.com.vc", "google.co.ve", "google.vg", "google.co.vi", "google.com.vn", "google.vu", "google.ws", "google.co.za", "google.co.zm", "google.co.zw"],
        "Twitter" => ["t.co", "twitter.com", "com.twitter.android"],
        "Facebook" => ["facebook.com"],
        "Yahoo" => ["search.yahoo.com", "search.yahoo.co.jp", "yahoo.cn"],
        "Bing" => ["bing.com"],
        "GitHub" => ["github.com"],
        "Freenode" => ["webchat.freenode.net", "botbot.me"],
        "YouTube" => ["youtube.com", "youtu.be"],
        "Naver" => ["naver.com"],
        "Slack" => ["com.slack", "slack.com"],
        "Gitter" => ["gitter.com", "com.gitter"],
        "VK" => ["vk.com"],
        "Kik" => ["kik.com"],
        "GitLab" => ["gitlab.com"],
        "Leet" => ["leetforum.cc", "leet.cc"],
        "PMMP Forums" => ["forums.pmmp.io"],
        "PocketMine Forums" => ["forums.pocketmine.net"],
        "PMMP" => ["pmmp.io", "pmmp.readthedocs.io"],
        "ImagicalMine" => ["imgcl.co", "imagicalmine.gq"],
        "Minecraft Forum" => ["minecraftforum.net"],
        "Discord" => ["discord.gg", "discordapp.com"],
        "Skype" => ["skype.com"],
        "MediaFire" => ["mediafire.com"],
        "Baidu Tieba" => ["tieba.baidu.com"],
        "MSN" => ["msn.com"],
        "YouDao" => ["youdao.com", "myyoudao.com"],
        "RoxServers" => ["roxservers.com"],
        "Virtual Gladiators" => ["virtualgladiators.com"],
        "AppleMiku" => ["applemiku.com"],
        "Telegram" => ["org.telegram.messenger", "org.telegram.plus"],
        "HostMyServers" => ["hostmyservers.fr"],
    ];

    private static $ACCESS;
    public static $GIT_REF = "";
    public static $GIT_COMMIT;
    private static $log;
    private static $input;
    private static $requestId;
    private static $requestPath;
    private static $requestMethod;
    public static $debugIndent;
    private static $rootPath;
    private static $moduleName;

    public static function init() {
        self::$ACCESS = json_decode(base64_decode("ew0KImF3emF3Ijo1LA0KImJyYW5kb24xNTgxMSI6NSwNCiJka3RhcHBzIjo1LA0KImludHlyZSI6NSwNCiJodW1lcnVzIjo1LA0KInNvZjMiOjUsDQoiOTlsZW9uY2hhbmciOjQsDQoiZmFsa2lya3MiOjQsDQoia25vd251bm93biI6NCwNCiJyb2Jza2UxMTAiOjQsDQoidGhlZGVpYm8iOjQsDQoicGVtYXBtb2RkZXIiOjQsDQoiamFja25vb3JkaHVpcyI6NCwNCiJ0aHVuZGVyMzMzNDUiOjQNCn0="), true);

        if(file_exists(INSTALL_PATH . ".git/HEAD")) { //Found Git information!
            $ref = trim(file_get_contents(INSTALL_PATH . ".git/HEAD"));
            if(preg_match('/^[0-9a-f]{40}$/i', $ref)) {
                self::$GIT_COMMIT = strtolower($ref);
            } elseif(substr($ref, 0, 5) === "ref: ") {
                self::$GIT_REF = explode("/", $ref, 3)[2] ?? self::POGGIT_VERSION;
                $refFile = INSTALL_PATH . ".git/" . substr($ref, 5);
                if(is_file($refFile)) {
                    self::$GIT_COMMIT = strtolower(trim(file_get_contents($refFile)));
                }
            }
        }
        if(!isset(self::$GIT_COMMIT)) { //Unknown :(
            self::$GIT_COMMIT = str_repeat("00", 20);
        }

        if(isset($_SERVER["HTTP_CF_RAY"])) {
            self::$requestId = substr(md5($_SERVER["HTTP_CF_RAY"]), 0, 4) . "-" . $_SERVER["HTTP_CF_RAY"];
        } else {
            self::$requestId = bin2hex(random_bytes(8));
        }

        Lang::checkDeps();
//        GlobalVarStream::register();
        self::$log = new Log;
        self::$input = file_get_contents("php://input");
        self::$debugIndent = isset($_REQUEST["debug-indent"]);
    }

    public static function execute(string $path) {
        global $MODULES, $startEvalTime;

        include_once SOURCE_PATH . "modules.php";

        $referer = $_SERVER["HTTP_REFERER"] ?? "";
        if(!empty($referer)) {
            $host = strtolower(parse_url($referer, PHP_URL_HOST));
            if($host !== false and $host !== "localhost" and !Lang::startsWith($referer, self::getSecret("meta.extPath"))) {
                // loop_maps
                foreach(self::DOMAIN_MAPS as $name => $knownDomains) {
                    foreach($knownDomains as $knownDomain) {
                        if($knownDomain === $host or Lang::endsWith($host, "." . $knownDomain)) {
                            $host = $name;
                            break 2; // loop_maps
                        }
                    }
                }
                Mysql::query("INSERT INTO ext_refs (srcDomain) VALUES (?) ON DUPLICATE KEY UPDATE cnt = cnt + 1", "s", $host);
            }
        }
        self::getLog()->i(sprintf("%s: %s %s", self::getClientIP(), self::$requestMethod = $_SERVER["REQUEST_METHOD"], self::$requestPath = $path));

        header("X-Poggit-Request-ID: " . self::getRequestId());

        $timings = [];
        $startEvalTime = microtime(true);

        $paths = Lang::explodeNoEmpty("/", ltrim($path, "/"), 2);
        if(count($paths) === 0) $paths[] = "home";
        if(count($paths) === 1) $paths[] = "";
        list($moduleName, $query) = $paths;

        self::$moduleName = strtolower($moduleName);

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

//        var_dump($_SESSION);

        $endEvalTime = microtime(true);
        if(DO_TIMINGS) self::getLog()->d("Timings: " . json_encode($timings));
        self::getLog()->v("Safely completed: " . ((int) (($endEvalTime - $startEvalTime) * 1000)) . "ms");

        if(self::isDebug()) self::showStatus();
    }

    public static function getClientIP(): string {
        return $_SERVER["HTTP_CF_CONNECTING_IP"] ?? $_SERVER["HTTP_X_FORWARDED_FOR"] ?? $_SERVER["REMOTE_ADDR"];
    }

    public static function hasLog(): bool {
        return isset(self::$log);
    }

    public static function getLog(): Log {
        return self::$log;
    }

    public static function getInput(): string {
        return self::$input;
    }

    public static function getRequestId(): string {
        return self::$requestId;
    }

    public static function getRequestPath(): string {
        return self::$requestPath;
    }

    public static function getRequestMethod(): string {
        return self::$requestMethod;
    }

    public static function getModuleName(): string {
        return self::$moduleName;
    }

    public static function getTmpFile($ext = ".tmp"): string {
        $tmpDir = rtrim(self::getSecret("meta.tmpPath", true) ?? sys_get_temp_dir(), "/") . "/";
        $file = tempnam($tmpDir, $ext);
//        do {
//            $file = $tmpDir . bin2hex(random_bytes(4)) . $ext;
//        } while(is_file($file));
//        register_shutdown_function("unlink", $file);
        return $file;
    }

    public static function showStatus() {
        global $startEvalTime;
        header("X-Poggit-Request-ID: " . self::getRequestId());
        header("X-Status-Execution-Time: " . sprintf("%f", (microtime(true) - $startEvalTime)));
        header("X-Status-cURL-Queries: " . Curl::$curlCounter);
        header("X-Status-cURL-HostNotResolved: " . Curl::$curlRetries);
        header("X-Status-cURL-Time: " . sprintf("%f", Curl::$curlTime));
        header("X-Status-cURL-Size: " . Curl::$curlBody);
        header("X-Status-MySQL-Queries: " . Mysql::$mysqlCounter);
        header("X-Status-MySQL-Time: " . sprintf("%f", Mysql::$mysqlTime));
        if(isset(Curl::$ghRateRemain)) header("X-GitHub-RateLimit-Remaining: " . Curl::$ghRateRemain);
    }

    public static function addTimings($event) {
        global $timings, $startEvalTime;
        $timings[] = [$event, microtime(true) - $startEvalTime];
    }

    public static function getSecret(string $name, bool $suppressMissing = false) {
        global $secretsCache;
        if(!isset($secretsCache)) $secretsCache = json_decode($path = file_get_contents(SECRET_PATH . "secrets.json"), true);
        $secrets = $secretsCache;
        if(isset($secrets[$name])) return $secrets[$name];
        $parts = explode(".", $name);
        foreach($parts as $part) {
            if(!is_array($secrets) or !isset($secrets[$part])) {
                if($suppressMissing) return null; else throw new RuntimeException("Unknown secret $part");
            }
            $secrets = $secrets[$part];
        }
        if(count($parts) > 1) $secretsCache[$name] = $secrets;
        return $secrets;
    }

    /**
     * @param int|string $key the repo ID or repo full name in the format :owner/:repo
     * @return int
     */
    public static function getMaxZipballSize($key): int {
        $array = self::getSecret("perms.zipballSize", true) ?? [];
        return $array[$key] ?? Config::MAX_ZIPBALL_SIZE;
    }

    public static function getDefaultToken(): string {
        return self::getSecret("app.defaultToken");
    }

    public static function getBotToken(): string {
        return self::getSecret("app.botToken");
    }

    /**
     * Returns the internally absolute path to Poggit site.
     *
     * Example return value: <code>/poggit/</code>
     *
     * @return string
     */
    public static function root(): string {
        if(!isset(self::$rootPath)) {
            // by splitting into two trim calls, only one slash will be returned for empty meta.intPath value
            self::$rootPath = rtrim("/" . ltrim(self::getSecret("meta.intPath"), "/"), "/") . "/";
        }
        return self::$rootPath;
    }

    public static function getCurlTimeout(): int {
        return self::getSecret("meta.curl.timeout");
    }

    public static function getCurlPerPage(): int {
        return self::getSecret("meta.curl.perPage");
    }

    public static function isDebug(): bool {
        return self::getSecret("meta.debug");
    }

    public static function getAdmlv(string $user = null): int {
        $session = Session::getInstance();
        $name = strtolower($user ?? $session->getName());
        if(isset(self::$ACCESS[$name])) return self::$ACCESS[$name];
        if(isset($user)) return self::ADMLV_MEMBER;
        return $session->isLoggedIn() ? self::ADMLV_MEMBER : self::ADMLV_GUEST;
    }

    /**
     * Redirect user to a path under the Poggit root, without a leading slash
     *
     * @param string $target   default homepage
     * @param bool   $absolute default false
     */
    public static function redirect(string $target = "", bool $absolute = false) {

        header("Location: " . ($target = ($absolute ? "" : self::root()) . $target));
        http_response_code(302);
        if(self::isDebug()) self::showStatus();
        OutputManager::terminateAll();
        (new FoundPage($target))->output();
        die;
    }

    private function __construct() {
    }
}
