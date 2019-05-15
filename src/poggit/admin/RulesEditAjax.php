<?php

/*
 * poggit
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

namespace poggit\admin;

use poggit\Meta;
use poggit\module\HtmlModule;
use poggit\utils\internet\Mysql;
use stdClass;
use function in_array;
use function json_encode;

class RulesEditAjax extends HtmlModule {
    public function output() {
        if(Meta::getAdmlv() < Meta::ADMLV_ADMIN) {
            $this->errorAccessDenied("Access denied");
            return;
        }

        $id = $this->param("id");
        $field = $this->param("fieldName");
        $new = $this->param("newText");

        if(!in_array($field, ["title", "content"])) {
            $this->errorBadRequest("Unsupported field $field");
        }

        Mysql::query("UPDATE submit_rules SET $field = ? WHERE id = ?", "ss", $new, $id);
        echo json_encode(new stdClass);
    }
}
