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

namespace poggit\module\releases\submit;

use poggit\module\Module;
use poggit\session\SessionUtils;

class PluginSubmitCallbackModule extends Module {

    public function getName() : string {
        return "release.submit.callback";
    }

    public function output() {
        $session = SessionUtils::getInstance();
        if(!$session->isLoggedIn()) $this->errorAccessDenied();
        foreach(["owner", "repo", "project", "buildClass", "build", "antiForge",
                    "name", "shortDesc", "version", "isPreRelease", "pluginIcon"] as $field) {
            if(!isset($_POST[$field])) $this->errorBadRequest("Missing POST field $field");
        }
        if($_POST["antiForge"] !== $session->getAntiForge()) $this->errorAccessDenied();
        // TODO validate stuff
    }
}
