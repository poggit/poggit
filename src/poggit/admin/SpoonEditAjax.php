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

use function is_string;
use poggit\Meta;
use poggit\module\AjaxModule;
use poggit\utils\internet\Mysql;
use poggit\utils\PocketMineApi;
use function apcu_delete;

class SpoonEditAjax extends AjaxModule {
    protected function impl() {
        if(Meta::getAdmlv() < Meta::ADMLV_ADMIN) {
            $this->errorAccessDenied();
            return;
        }

        $spoon = (int) $this->param("spoon");
        $field = $this->param("field");
        $to = $this->param("to");

        if($field === "incompatible") {
            $to = (int) $this->param("to");
        }else{
            $this->errorBadRequest("Unknown field $field");
        }

        Mysql::query("UPDATE known_spoons SET `$field` = ? WHERE id = ?", is_string($to) ? "si" : "ii", $to, $spoon);

        apcu_delete(PocketMineApi::KEY_VERSIONS);

        echo json_encode([
            "message" => "Changed $field for #$spoon to $to",
        ]);
    }
}
