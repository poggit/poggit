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

declare(strict_types=1);

namespace poggit\module;

use poggit\account\Session;
use poggit\Mbd;
use poggit\Meta;
use ReflectionClass;
use function array_merge;
use function implode;
use function json_encode;
use function strlen;

abstract class HtmlModule extends Module {
    protected function headIncludes(string $title, $description = "", $type = "website", string $shortUrl = "", array $extraKeywords = [], string $image = null) {
        global $requestPath;
        ?>
      <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
      <meta name="description"
            content="<?= Mbd::esq($title) === "Poggit" ? "Poggit: The PocketMine Plugin Platform" : Mbd::esq($title) ?>">
      <meta name="keywords"
            content="<?= implode(",", array_merge([Mbd::esq($title)], $extraKeywords)) ?>,plugin,PocketMine,pocketmine plugins,MCPE plugins,Poggit,PocketMine-MP,PMMP"/>
      <meta property="og:site_name" content="Poggit"/>
      <meta property="og:image" content="<?= $image ?? (Meta::getSecret("meta.extPath") . (date("M j") === "Apr 1" ? "res/mrs-poggit.png" : "res/poggit-icon.png")) ?>"/>
      <meta property="og:title" content="<?= Mbd::esq($title) ?>"/>
      <meta property="og:type" content="<?= $type ?>"/>
      <meta property="og:url" content="<?= strlen($shortUrl) > 0 ? Mbd::esq($shortUrl) :
          (Meta::getSecret("meta.extPath") . Mbd::esq($requestPath === "/" ? "" : $requestPath ?? "")) ?>"/>
      <meta name="twitter:card" content="summary"/>
      <meta name="twitter:site" content="poggitci"/>
      <meta name="twitter:title" content="<?= Mbd::esq($title) ?>"/>
      <meta name="twitter:description" content="<?= Mbd::esq($description) ?>"/>
      <meta name="theme-color" content="#292b2c">
      <meta name="apple-mobile-web-app-capable" content="yes">
      <meta name="mobile-web-app-capable" content="yes">
      <link type="image/x-icon" rel="icon" href="<?= Meta::root() ?>res/<?= date("M j") === "Apr 1" ? "mrs-poggit" : "poggit-icon" ?>.ico">
        <?php
        ResModule::echoSessionJs(true); // prevent round-trip -> faster loading; send before GA
//        @formatter:off
        ?>
      <!--suppress JSUnresolvedFunction -->
        <script>
        <?php Mbd::analytics() ?>
        <?php Mbd::gaCreate() ?>
        ga('set', 'dimension1', <?= json_encode(Session::getInstance()->isLoggedIn() ? "Member" : "Guest") ?>);
        ga('set', 'dimension2', <?= json_encode(Meta::ADMLV_MAP[Meta::getAdmlv()]) ?>);
        ga('set', 'dimension3', <?= json_encode((new ReflectionClass($this instanceof VarPageModule ? $this->varPage : $this))->getShortName()) ?>);
        ga('send', 'pageview');
      </script>
      <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-alpha.6/css/bootstrap.min.css">
      <link rel="stylesheet" href="https://ajax.googleapis.com/ajax/libs/jqueryui/1.13.2/themes/base/jquery-ui.css">
        <?php
//        @formatter:on
        $session = Session::getInstance();
        if(!$session->isLoggedIn()) self::includeCss("style.min"); //Default Light.
        if($session->getLogin()["opts"]->darkMode ?? false) self::includeCss("style-dark.min");
        else self::includeCss("style.min");
        self::includeCss("toggles.min");
        self::includeCss("toggles-light.min");
        self::includeCss("jquery.paginate.min");
    }

}
