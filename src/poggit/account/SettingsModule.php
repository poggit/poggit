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

use poggit\Mbd;
use poggit\Meta;
use poggit\module\Module;

class SettingsModule extends Module {
    public static $OPTIONS = [
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
        "allowSu" => [
            "default" => false,
            "brief" => "Allow admin su",
            "details" => "Allow Poggit admins to login on Poggit as you. Poggit admins may ask you to enable this if you are encountering bugs on Poggit."
        ],
    ];

    private $opts;

    public function getName(): string {
        return "settings";
    }

    public function output() {
        $session = Session::getInstance();
        if(!$session->isLoggedIn()) Meta::redirect("login");
        $this->opts = $session->getLogin()["opts"];
        ?>
        <html>
        <head prefix="og: http://ogp.me/ns# fb: http://ogp.me/ns/fb# object: http://ogp.me/ns/object# article: http://ogp.me/ns/article# profile: http://ogp.me/ns/profile#">
            <title>Account Settings | Poggit</title>
            <?php $this->headIncludes("Account Settings") ?>
            <script>
                function onToggleOpt(cb, name) {
                    cb.disabled = true;
                    ajax("opt.toggle", {
                        data: {
                            name: name,
                            value: cb.checked ? "true" : "false"
                        },
                        success: function(data) {
                            cb.disabled = false;
                        }
                    });
                }
            </script>
        </head>
        <body>
        <?php $this->bodyHeader() ?>
        <div id="body">
            <h1>Account Settings</h1>
            <?php
            foreach(self::$OPTIONS as $name => $option) {
                ?>
                <div class="cbinput">
                    <input type="checkbox" <?= ($this->opts->{$name} ?? $option["default"]) ? "checked" : "" ?>
                           onclick='onToggleOpt(this, <?= json_encode($name) ?>);'/>
                    <?= $option["brief"] ?> &nbsp; <?php Mbd::hint($option["details"]) ?>
                </div>
                <?php
            }
            ?>
        </div>
        <?php $this->bodyFooter() ?>
        <?php $this->includeBasicJs(); ?>
        </body>
        </html>
        <?php
    }

    private function makeOption(string $name, bool $default, string $brief, string $details) {

    }
}
