<?php

/*
 * Poggit
 *
 * Copyright (C) 2017 Poggit
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

namespace poggit\module\releases;

use poggit\module\ajax\AjaxModule;
use poggit\release\PluginRelease;

class RelSubValidateAjax extends AjaxModule {
    protected function impl() {
        $name = $_POST["pluginName"];
        $ok = PluginRelease::validatePluginName($name, $message);
        echo json_encode(["ok" => $ok, "message" => $message]);
    }

    public function getName(): string {
        return "ajax.relsubvalidate";
    }
}
