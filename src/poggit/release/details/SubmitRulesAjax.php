<?php

/*
 * ticgen
 *
 * Copyright (C) 2018 SOFe
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


namespace poggit\release\details;

use poggit\module\AjaxModule;
use poggit\utils\internet\Mysql;
use function header;
use function json_encode;

class SubmitRulesAjax extends AjaxModule {
    protected function impl() {
        header("Content-Type: application/json");
        $output = [];
        foreach(Mysql::query("SELECT id, title, content, uses FROM submit_rules ORDER BY uses DESC") as $row) {
            $output[$row["id"]] = [
                "id" => $row["id"],
                "title" => $row["title"],
                "content" => $row["content"],
                "uses" => $row["uses"],
            ];
        }
        echo json_encode($output);
    }

    protected function needLogin(): bool {
        return false;
    }
}
