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

namespace poggit\module;

use poggit\account\SessionUtils;
use poggit\errdoc\AccessDeniedPage;
use poggit\errdoc\BadRequestPage;
use poggit\errdoc\NotFoundPage;
use poggit\errdoc\SimpleNotFoundPage;
use poggit\Mbd;
use poggit\Poggit;
use poggit\utils\OutputManager;

abstract class Module {
    /** @var Module|null */
    public static $currentPage = null;

    /** @var string */
    private $query;

    public function __construct(string $query) {
        $this->query = $query;
    }

    public function getQuery() {
        return $this->query;
    }

    public abstract function getName(): string;

    public function getAllNames(): array {
        return [$this->getName()];
    }

    public abstract function output();

    public function errorNotFound(bool $simple = false) {
        OutputManager::terminateAll();
        if($simple) {
            (new SimpleNotFoundPage(""))->output();
        } else {
            (new NotFoundPage($this->getName() . "/" . $this->query))->output();
        }
        die;
    }

    public function errorAccessDenied(string $details = null) {
        OutputManager::terminateAll();
        $page = new AccessDeniedPage($this->getName() . "/" . $this->query);
        if($details !== null) $page->details = $details;
        $page->output();
        die;
    }

    public function errorBadRequest(string $message) {
        OutputManager::terminateAll();
        (new BadRequestPage($message))->output();
        die;
    }

    protected function headIncludes(string $title, $description = "", $type = "website", string $shortUrl = "") {
        global $requestPath;
        ?>
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <meta name="description" content="PocketMine Plugin Development &nbsp; Release Platform for MCPE Servers">
        <meta name="keywords"
              content="Minecraft,PocketMine,Pocket Mine,PocketMine-MP,PMMP,Pocket Edition,MCPE,Online,PHP,Plugin,API,Poggit"/>
        <meta property="og:site_name" content="Poggit"/>
        <meta property="og:image" content="<?= Poggit::getSecret("meta.extPath") ?>res/poggit.png"/>
        <meta property="og:title" content="<?= Mbd::esq($title) ?>"/>
        <meta property="og:type" content="<?= $type ?>"/>
        <meta property="og:url" content="<?= strlen($shortUrl) > 0 ? Mbd::esq($shortUrl) :
            (Poggit::getSecret("meta.extPath") . Mbd::esq($requestPath === "/" ? "" : $requestPath ?? "")) ?>"/>
        <meta name="twitter:card" content="summary"/>
        <meta name="twitter:site" content="poggitci"/>
        <meta name="twitter:title" content="<?= Mbd::esq($title) ?>"/>
        <meta name="twitter:description" content="<?= Mbd::esq($description) ?>"/>
        <meta name="theme-color" content="#292b2c">
        <script src="//code.jquery.com/jquery-1.12.4.min.js" type="text/javascript"></script>
        <script src="//code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
        <link type="text/css" rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.min.css">
        <script src="//malsup.github.io/jquery.form.js"></script>
        <link type="image/x-icon" rel="icon" href="<?= Poggit::getRootPath() ?>res/poggit.ico">
        <?php
        $this->includeCss("bootstrap.min");
        $this->includeCss("style");
        $this->includeJs("mobile");
        $this->includeJs("jQuery-UI-Dialog-extended");
        $this->includeJs("std");
        $this->includeJs("bootstrap");
        if(!SessionUtils::getInstance()->tosHidden()) $this->includeJs("remindTos");
    }

    protected function bodyHeader() {
        $session = SessionUtils::getInstance();
        ?>
        <div id="header" class="container-fluid">
            <nav class="navbar navbar-toggleable-md navbar-inverse bg-inverse fixed-top" role="navigation">
                <div class="tabletlogo">
                    <div class="navbar-brand tm">
                        <a href="<?= Poggit::getRootPath() ?>"><img class="logo"
                                                                    src="<?= Poggit::getRootPath() ?>res/poggit.png"/>
                            Poggit</a></div>
                    <button class="navbar-toggler navbar-toggler-right mr-auto" type="button" data-toggle="collapse"
                            data-target="#navbarNavAltMarkup" aria-controls="navbarNavAltMarkup" aria-expanded="false"
                            aria-label="Toggle navigation">
                        <span class="navbar-toggler-icon"></span>
                    </button>

                </div>
                <ul class="navbar-nav navbuttons collapse navbar-collapse">
                    <li class="nav-item navbutton" data-target="">Home</li>
                    <li class="nav-item navbutton" data-target="ci">CI</li>
                    <li class="nav-item navbutton" data-target="get.pmmp/html">
                        <nobr>PMMP</nobr>
                    </li>
                    <li class="nav-item navbutton" data-target="pi">Release</li>
                    <li class="nav-item navbutton" data-target="review">Review</li>
                    <li class="nav-item navbutton" data-target="help">Help</li>
                </ul>
                <div id="navbarNavAltMarkup" class="navbuttons collapse navbar-collapse">
                    <ul class="navbar-nav navbuttons collapse navbar-collapse">
                        <?php if($session->isLoggedIn()) { ?>
                            <li class="nav-item loginbuttons"><span
                                        onclick="logout()">Logout as <?= htmlspecialchars($session->getLogin()["name"]) ?></span>
                            </li>
                            <li class="nav-item loginbuttons"><span
                                        onclick="login(undefined, true)">Change Scopes</span>
                            </li>
                        <?php } else { ?>
                            <li class="nav-item loginbuttons"><span onclick='login()'>Login with GitHub</span></li>
                            <li class="nav-item loginbuttons"><span onclick="login(undefined, true)">Custom Login</span>
                            </li>
                        <?php } ?>
                        <?php if(Poggit::getAdmlv($session->getName()) === Poggit::ADM) { ?>
                            <li class="loginbuttons"><span
                                        onclick='ajax("login.su", {data: {target: prompt("su")}, success: function() { window.location.reload(true); }})'><code>su</code></span>
                            </li>
                        <?php } ?>
                    </ul>
                </div>
            </nav>
        </div>
        <?php
    }

    protected function bodyFooter() {
        ?>
        <script async src="//platform.twitter.com/widgets.js" charset="utf-8"></script>
        <div id="footer">
            <ul class="footernavbar">
                <li>Powered by Poggit <?= Poggit::POGGIT_VERSION ?>
                    <?php if(Poggit::isDebug()) { ?>
                        (<a href="https://github.com/poggit/poggit/tree/<?= Poggit::$GIT_COMMIT ?>">
                            <?= substr(Poggit::$GIT_COMMIT, 0, 7) ?>
                        </a>)
                    <?php } ?>
                </li>
                <li>&copy; <?= date("Y") ?> Poggit</li>
                <li><?= Poggit::$onlineUsers ?? 0 ?> online</li>
            </ul>
            <ul class="footernavbar">
                <li><a href="<?= Poggit::getRootPath() ?>tos">Terms of Service</a></li>
                <li><a target="_blank" href="https://gitter.im/poggit/Lobby">Contact Us</a></li>
                <li><a target="_blank" href="https://github.com/poggit/poggit">Source Code</a></li>
                <li><a target="_blank" href="https://github.com/poggit/poggit/issues">Report Bugs</a></li>
                <li><a href="https://twitter.com/poggitci" class="twitter-follow-button" data-show-screen-name="false"
                       data-show-count="true">Follow @poggitci</a></li>
                <li><a href="#" onclick="$('html, body').animate({scrollTop: 0},500);">Back to Top</a></li>
            </ul>
        </div>
        <?php
    }

    public function includeJs(string $fileName) {
//        if(isset($_REQUEST["xIncludeAssetsDirect"])) {
//            echo "<script>";
//            readfile(JS_DIR . $fileName . ".js");
//            echo "</script>";
//            return;
//        }
        ?>
        <script type="text/javascript" src="<?= Poggit::getRootPath() ?>js/<?= Mbd::esq($fileName) ?>.js"></script>
        <?php
    }

    public function includeCss(string $fileName) {
//        if(isset($_REQUEST["xIncludeAssetsDirect"])) {
//            echo "<style>";
//            readfile(RES_DIR . $fileName . ".css");
//            echo "</style>";
//            return;
//        }
        ?>
        <link type="text/css" rel="stylesheet" href="<?= Poggit::getRootPath() ?>res/<?= Mbd::esq($fileName) ?>.css">
        <?php
    }
}
