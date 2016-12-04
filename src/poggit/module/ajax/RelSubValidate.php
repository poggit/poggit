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
        $name = $_POST["pluginName"];
        if(!preg_match('%^[A-Za-z0-9_]{2,}$%', $name)) {
            echo json_encode(["ok" => false, "message" => "Name must be at least 2 characters long and only consist of A-Z, a-z, 0-9 or _"]);
            return;
        }
        $rows = Poggit::queryAndFetch("SELECT COUNT(releases.name) AS dups FROM releases WHERE name LIKE ? AND releaseId != ?", "si", $_POST["pluginName"] . "%", (int) ($_POST["except"] ?? -1));
        $dups = (int) $rows[0]["dups"];
        if($dups > 0) {
            echo json_encode(["ok" => false, "message" => "There are $dups plugins starting with '$name'", JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE]);
            return;
        }
        echo json_encode(["ok" => true, "message" => "Great name!"]);
    }

    public function getName(): string {
        return "ajax.relsubvalidate";
    }
}
