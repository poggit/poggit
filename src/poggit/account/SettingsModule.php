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

use poggit\module\Module;
use poggit\Poggit;

class SettingsModule extends Module {
    public function getName(): string {
        return "settings";
    }

    public function output() {
        $session = SessionUtils::getInstance();
        if(!$session->isLoggedIn()) {
            Poggit::redirect("login");
        }
        $opts = $session->getInstance()->getLogin()["opts"];
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
            <div class="cbinput">
                <input type="checkbox" <?= ($opts->allowSu ?? false) ? "checked" : "" ?>
                       onclick='onToggleOpt(this, "allowSu")'/>
                Allow admin su &nbsp; <sup class="hover-title"
                                           title="Allow Poggit admins to login and do everything on Poggit on behalf of yor account, limited to Poggit">(?)</sup>
            </div>
        </div>
        <?php $this->bodyFooter() ?>
        </body>
        </html>
        <?php
    }
}
