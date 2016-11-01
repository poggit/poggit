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

namespace poggit\module;

use poggit\module\error\AccessDeniedPage;
use poggit\module\error\BadRequestPage;
use poggit\module\error\NotFoundPage;
use poggit\module\error\SimpleNotFoundPage;
use poggit\output\OutputManager;
use poggit\Poggit;
use poggit\session\SessionUtils;

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

    public abstract function getName() : string;

    public function getAllNames() : array {
        return [$this->getName()];
    }

    public abstract function output();

    protected function errorNotFound(bool $simple = false) {
        OutputManager::terminateAll();
        if($simple) {
            (new SimpleNotFoundPage(""))->output();
        } else {
            (new NotFoundPage($this->getName() . "/" . $this->query))->output();
        }
        die;
    }

    protected function errorAccessDenied() {
        OutputManager::terminateAll();
        (new AccessDeniedPage($this->getName() . "/" . $this->query))->output();
        die;
    }

    protected function errorBadRequest(string $message) {
        OutputManager::terminateAll();
        (new BadRequestPage($message))->output();
        die;
    }

    protected function bodyHeader() {
        $session = SessionUtils::getInstance();
        ?>
        <div id="header">
            <ul class="navbar">
                <li style="padding-right: 0; vertical-align: middle;">
                    <img src="<?= Poggit::getRootPath() ?>res/poggit.png" width="32"></li>
                <li><span class="tm">Poggit</span></li>
                <li class="navbutton" data-target="">Home</li>
                <li class="navbutton" data-target="ci">CI</li>
                <li class="navbutton" data-target="pi">Release</li>
                <li class="navbutton extlink" data-target="https://github.com/poggit/poggit">GitHub</li>
                <div style="float: right; padding-right: 50px">
                    <?php if($session->isLoggedIn()) { ?>
                        <li><span onclick="logout()" class="action">Logout as <?= $session->getLogin()["name"] ?></span>
                        </li>
                    <?php } else { ?>
                        <li>
                            <span class="action" onclick='login()'>Login with GitHub</span>
                        </li>
                    <?php } ?>
                </div>
            </ul>
        </div>
        <?php
    }

    protected function headIncludes(string $title, $description = "", $type = "website", string $shortUrl = "") {
        global $requestPath;
        ?>
        <meta property="og:site_name" content="Poggit"/>
        <meta property="og:image" content="<?= Poggit::getSecret("meta.extPath") ?>res/poggit.png"/>
        <meta property="og:title" content="<?= $title ?>"/>
        <meta property="og:type" content="<?= $type ?>"/>
        <meta property="og:url" content="<?= strlen($shortUrl) > 0 ? $shortUrl :
            (Poggit::getSecret("meta.extPath") . ($requestPath === "/" ? "" : $requestPath)) ?>"/>
        <meta name="twitter:card" content="summary"/>
        <meta name="twitter:site" content="poggitci"/>
        <meta name="twitter:title" content="<?= $title ?>"/>
        <meta name="twitter:description" content="<?= $description ?>"/>

        <script src="//code.jquery.com/jquery-1.12.4.min.js"></script>
        <script src="//code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
        <link type="text/css" rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.min.css">
        <script src="//malsup.github.io/jquery.form.js"></script>
        <link type="text/css" rel="stylesheet" href="<?= Poggit::getRootPath() ?>res/style.css">
        <link type="image/x-icon" rel="icon" href="<?= Poggit::getRootPath() ?>res/poggit.ico">
        <?php
        $this->includeJs("std");
        if(!SessionUtils::getInstance()->tosHidden()) $this->includeJs("remindTos");
    }

    protected function includeJs(string $fileName) {
        ?>
        <script src="<?= Poggit::getRootPath() ?>js/<?= $fileName ?>.js"></script>
        <?php
    }

    protected function includeCss(string $fileName) {
        ?>
        <link type="text/css" rel="stylesheet" href="<?= Poggit::getRootPath() ?>res/<?= $fileName ?>.css">
        <?php
    }
}
