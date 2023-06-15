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

namespace poggit\module;

use poggit\account\Session;
use poggit\ci\ui\BuildModule;
use poggit\errdoc\AccessDeniedPage;
use poggit\errdoc\BadRequestPage;
use poggit\errdoc\NotFoundPage;
use poggit\errdoc\SimpleNotFoundPage;
use poggit\Meta;
use poggit\release\index\ReleaseListModule;
use poggit\utils\OutputManager;
use function bin2hex;
use function date;
use function filesize;
use function htmlspecialchars;
use function json_encode;
use function random_bytes;
use function readfile;
use function substr;
use const poggit\JS_DIR;
use const poggit\RES_DIR;

abstract class Module {
    /** @var Module|null */
    public static $currentPage = null;

    public static $jsList = [
        "toggles",
        "jquery.form",
        "mobile",
        "std",
        "jquery.paginate",
    ];

    /** @var string */
    private $query;

    public function __construct(string $query) {
        $this->query = $query;
    }

    public function getQuery(): string {
        return $this->query;
    }

    public abstract function output();

    public function errorNotFound(bool $simple = false) {
        Session::getInstance();
        OutputManager::terminateAll();
        if($simple) {
            (new SimpleNotFoundPage(""))->output();
        } else {
            (new NotFoundPage(Meta::getModuleName() . "/" . $this->query))->output();
        }
        die;
    }

    public function errorAccessDenied(string $details = null) {
        Session::getInstance(); // init session cache limiter
        OutputManager::terminateAll();
        $page = new AccessDeniedPage(Meta::getModuleName() . "/" . $this->query);
        if($details !== null) $page->details = $details;
        $page->output();
        die;
    }

    public function errorBadRequest(string $message, bool $escape = true) {
        Session::getInstance();
        OutputManager::terminateAll();
        (new BadRequestPage($message, $escape))->output();
        die;
    }

    protected function flushJsList(bool $min = true) {
        ?>
      <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
      <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-alpha.6/js/bootstrap.min.js"></script>
      <script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js"></script>
        <?php
        if(Meta::isDebug()) $min = false;
        foreach(self::$jsList as $script) {
            self::includeJs($script . ($min ? ".min" : ""));
        }
    }

    protected function bodyHeader() {
        $session = Session::getInstance();
        ?>
      <div id="header" class="container-fluid">
        <nav class="navbar navbar-toggleable-md navbar-inverse bg-inverse fixed-top" role="navigation">
          <div class="tabletlogo">
            <div class="navbar-brand tm">
              <a href="<?= Meta::root() ?>">
                <img class="logo" src="<?= Meta::root() ?>res/<?= date("M j") === "Apr 1" ? "mrs-poggit" : "poggit-icon" ?>.png"/>
                Poggit
                  <?php if(date("M j") === "Apr 1") { ?>
                    <sub style="padding-left: 5px;">Mrs.</sub>
                  <?php } elseif(Meta::$GIT_REF !== "" and Meta::$GIT_REF !== "master" and Meta::$GIT_REF !== "deploy" and Meta::$GIT_REF !== "beta") { ?>
                    <sub style="padding-left: 5px;"><?= Meta::$GIT_REF === "tmp" ? "test" : Meta::$GIT_REF ?></sub>
                  <?php } ?>
              </a></div>
            <button class="navbar-toggler navbar-toggler-right mr-auto" type="button" data-toggle="collapse"
                    data-target="#navbarNavAltMarkup" aria-controls="navbarNavAltMarkup" aria-expanded="false"
                    aria-label="Toggle navigation">
              <span class="navbar-toggler-icon"></span>
            </button>

          </div>
          <div id="navbarNavAltMarkup" class="navbar-right navbuttons collapse navbar-collapse">
            <div class="navbar-middle">
              <ul class="navbar-nav navbuttons collapse navbar-collapse">
                <li class="nav-item navbutton" data-target="">Home</li>
                <li class="nav-item navbutton" data-target="plugins"><?= ReleaseListModule::DISPLAY_NAME ?></li>
                <li class="nav-item navbutton" data-target="ci/recent"><?= BuildModule::DISPLAY_NAME ?></li>
                  <?php if(Meta::getAdmlv() >= Meta::ADMLV_REVIEWER) { ?>
                    <li class="nav-item navbutton" data-target="review">Review</li>
                  <?php } ?>
                <li class="nav-item navbutton" data-target="faq">FAQ</li>
                <!--                        <li class="nav-item navbutton extlink" data-target="https://poggit.github.io/support">Help</li>-->
                <!-- TODO Finish the Help page, then add this back -->
              </ul>
            </div>
            <ul class="navbar-nav">
                <?php if($session->isLoggedIn()) { ?>
                    <?php if(Meta::getAdmlv($session->getName()) === Meta::ADMLV_ADMIN &&
                        ($session->getLogin()["opts"]->allowSu ?? false)) { ?>
                    <li class="login-buttons">
                      <span
                          onclick='ajax("login.su", {data: {target: prompt("su")}, success: function() { window.location.reload(true); }})'><code>su</code></span>
                    </li>
                    <?php } ?>
                  <li class="nav-item login-buttons">
                    <span onclick='location = <?= json_encode(Meta::root() . "settings") ?>;'>Settings</span>
                  </li>
                  <li class="nav-item login-buttons"><span onclick="logout()">Logout</span></li>
                  <div><a target="_blank"
                          href="https://github.com/<?= htmlspecialchars($session->getName()) ?>?tab=repositories">
                      <img width="20" height="20"
                           src="https://github.com/<?= htmlspecialchars($session->getName()) ?>.png" onerror="this.src='/res/ghMark.png'; this.onerror=null;"/></a></div>
                <?php } else { ?>
                  <li class="nav-item login-buttons"><span onclick='login()'>Login with GitHub</span></li>
                  <li class="nav-item login-buttons"><span onclick="login(undefined, true)">Custom Login</span>
                  </li>
                <?php } ?>
            </ul>
          </div>
        </nav>
      </div>
        <?php if(!$session->tosHidden()) { ?>
        <div id="remindTos">
          <p>By continuing to use this site, you agree to the <a href='<?= Meta::root() ?>tos'>Terms of
              Service</a> of this website, including usage of cookies.</p>
          <p><span class='action' onclick='hideTos()'>OK, Don't show this again</span></p>
        </div>
        <?php } ?>
        <?php
    }

    protected function bodyFooter() {
        ?>
      <script async src="//platform.twitter.com/widgets.js" charset="utf-8"></script>
      <div id="footer">
        <ul class="footer-navbar">
          <li>Powered by Poggit <?= !Meta::isDebug() ? Meta::POGGIT_VERSION :
                  ("<a href='https://github.com/poggit/poggit/tree/" . Meta::$GIT_REF . "'>" . Meta::$GIT_REF . "</a>") ?>
              <?php if(Meta::isDebug()) { ?>
                (@<a href="https://github.com/poggit/poggit/tree/<?= Meta::$GIT_COMMIT ?>"><?=
                      substr(Meta::$GIT_COMMIT, 0, 7) ?></a>)
              <?php } ?>
          </li>
          <li id="online-user-count"></li>
          <li>&copy; <?= date("Y") ?> Poggit</li>
          <span id="flat-cp">Some icons by www.freepik.com and <a href="https://icons8.com">Icon pack by Icons8</a></span>
        </ul>
        <ul class="footer-navbar">
          <li><a href="<?= Meta::root() ?>tos">Terms of Service</a></li>
          <li><a target="_blank" href="<?= Meta::getSecret("discord.serverInvite") ?>">Contact @ Discord</a></li>
          <li><a target="_blank" href="https://github.com/poggit/poggit">Source Code</a></li>
          <li><a target="_blank" href="https://github.com/poggit/poggit/issues">Bugs / Suggestions</a></li>
          <li><a href="https://twitter.com/poggitci" class="twitter-follow-button" data-show-screen-name="false"
                 data-show-count="true">Follow @poggitci</a></li>
          <li><a href="#" onclick="$('html, body').animate({scrollTop: 0},500);">Back to Top</a></li>
        </ul>
      </div>
        <?php
    }

    public static function queueJs(string $fileName) {
        self::$jsList[] = $fileName;
    }

    public static function includeJs(string $fileName, bool $async = false) {
        if(isset($_REQUEST["debug-include-assets-direct"]) || filesize(JS_DIR . $fileName . ".js") < 4096) {
            echo "<script>//$fileName.js\n";
            readfile(JS_DIR . $fileName . ".js");
            echo "</script>";
            return;
        }
        $noResCache = Meta::getSecret("meta.noResCache", true) ?? false;
        $prefix = "/" . ($noResCache ? substr(bin2hex(random_bytes(4)), 0, 7) : substr(Meta::$GIT_COMMIT, 0, 7));
        $src = Meta::root() . "js/{$fileName}.js{$prefix}";
        ?>
      <script type="text/javascript"<?= $async ? " async" : "" ?> src="<?= $src ?>"></script>
        <?php
    }

    public static function includeCss(string $fileName) {
        if(isset($_REQUEST["debug-include-assets-direct"]) || filesize(RES_DIR . $fileName . ".css") < 4096) {
            echo "<style>";
            readfile(RES_DIR . $fileName . ".css");
            echo "</style>";
            return;
        }
        $noResCache = Meta::getSecret("meta.noResCache", true) ?? false;
        $prefix = "/" . ($noResCache ? substr(bin2hex(random_bytes(4)), 0, 7) : substr(Meta::$GIT_COMMIT, 0, 7));
        $href = Meta::root() . "res/{$fileName}.css{$prefix}";
        ?>
      <link type="text/css" rel="stylesheet" href="<?= $href ?>">
        <?php
    }

    protected function param(string $name, array $array = null) {
        if($array === null) $array = $_REQUEST;
        if(!isset($array[$name])) $this->errorBadRequest("Missing parameter '$name'");
        return $array[$name];
    }
}
