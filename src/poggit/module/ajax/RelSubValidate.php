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

namespace poggit\module\ajax;

use poggit\Poggit;

class RelSubValidate extends AjaxModule {

    protected function impl() {
        if (!ctype_alnum($_POST["pluginname"])) {
            echo json_encode(["plugincount" => "-1"], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return;
        }
        $rows = Poggit::queryAndFetch("SELECT 
            projects.name FROM projects WHERE projects.name LIKE '%" . $_POST["pluginname"] . "%'");
        echo json_encode(["plugincount" => (count($rows))], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function getName(): string {
        return "ajax.relsubvalidate";
    }

}
