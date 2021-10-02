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

use poggit\Mbd;
use poggit\Meta;
use poggit\module\HtmlModule;
use poggit\module\Module;

class SettingsModule extends HtmlModule {
    public static function getOptions(): array {
        $root = Meta::root();
        return [
            "makeTabs" => [
                "default" => true,
                "brief" => "Show description in tabs",
                "details" => "Poggit will try to split the plugin description into multiple tabs."
            ],
            "usePages" => [
                "default" => true,
                "brief" => "Enable pagination",
                "details" => "If you disable this option, all releases will be shown on a single page in the plugin list."
            ],
            "showIcons" => [
                "default" => true,
                "brief" => "Show plugin icons",
                "details" => "If you disable this option, all plugin icons will be displayed as the default plugin icon to save data: <img src='{$root}res/defaultPluginIcon2.png'/>"
            ],
            "autoLogin" => [
                "default" => true,
                "brief" => "Automatically re-login if session expired",
                "details" => "Your login expires after 24 hours of inactivity. Enabling this will automatically log you in again when you return to Poggit, without requiring you to click the \"Login with GitHub\" button again."
            ],
            "allowSu" => [
                "default" => false,
                "brief" => "Allow admin su",
                "details" => "Allow Poggit admins to login on Poggit as you. Poggit admins may ask you to enable this if you are encountering bugs on Poggit."
            ],
            "darkMode" => [
                "default" => false,
                "brief" => "Enable dark mode",
                "details" => "Changes the theme of poggit to dark mode, saving your eyes from the pain of light mode."
            ],
        ];
    }

    private $opts;

    public function output() {
        $session = Session::getInstance();
        if(!$session->isLoggedIn()) Meta::redirect("login");
        $this->opts = $session->getLogin()["opts"];
        ?>
      <html>
      <head
          prefix="og: http://ogp.me/ns# fb: http://ogp.me/ns/fb# object: http://ogp.me/ns/object# article: http://ogp.me/ns/article# profile: http://ogp.me/ns/profile#">
        <title>Account Settings | Poggit</title>
          <?php $this->headIncludes("Account Settings") ?>
      </head>
      <body>
      <?php $this->bodyHeader() ?>
      <div id="body">
        <h1>Account Settings</h1>
          <?php
          foreach(self::getOptions() as $name => $option) {
              ?>
            <div class="cbinput">
              <input class="settings-cb"
                     type="checkbox" <?= ($this->opts->{$name} ?? $option["default"]) ? "checked" : "" ?>
                     data-name="<?= Mbd::esq($name) ?>"/>
                <?= $option["brief"] ?> &nbsp; <?php Mbd::hint($option["details"], true) ?>
            </div>
              <?php
          }
          ?>
        <hr/>
        <h3>GitHub Integration</h3>
        <p><span class="action" onclick="login(undefined, true)">Authorize more permissions to Poggit</span></p>
      </div>
      <?php
      $this->bodyFooter();
      Module::queueJs("settings");
      $this->flushJsList(); ?>
      </body>
      </html>
        <?php
    }
}
