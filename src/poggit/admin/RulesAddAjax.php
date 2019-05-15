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
use poggit\module\AjaxModule;
use poggit\release\Release;
use poggit\utils\internet\Mysql;
use poggit\utils\PocketMineApi;
use function apcu_delete;
use function array_filter;
use function explode;
use function json_encode;
use stdClass;
use function trim;

class RulesAddAjax extends AjaxModule {
    protected function impl() {
        if(Meta::getAdmlv() < Meta::ADMLV_ADMIN) {
            $this->errorAccessDenied();
            return;
        }

        Mysql::query("INSERT INTO submit_rules (id, title, content, uses) VALUES (?, ?, ?, 0)", "sss",
            $this->param("id"), $this->param("title"), $this->param("content"));

        echo json_encode(new stdClass);
    }
}
