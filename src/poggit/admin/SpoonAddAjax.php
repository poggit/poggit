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
use function trim;

class SpoonAddAjax extends AjaxModule {
    protected function impl() {
        if(Meta::getAdmlv() < Meta::ADMLV_ADMIN) {
            $this->errorAccessDenied();
            return;
        }

        $name = $this->param("name");
        $incompatible = (int) $this->param("incompatible");

        echo json_encode(["id" => self::addSpoon($name, $incompatible)]);
    }

    public static function addSpoon(string $name, int $incompatible): int {
        apcu_delete(PocketMineApi::KEY_VERSIONS);
        apcu_delete(PocketMineApi::KEY_PROMOTED_COMPAT);

        $last = Mysql::query("SELECT name FROM known_spoons ORDER BY id DESC LIMIT 1")[0]["name"];

        $id = Mysql::query("INSERT INTO known_spoons (name, incompatible) VALUES (?, ?)", "si", $name, $incompatible)->insert_id;

        Mysql::query("UPDATE spoon_prom SET value = ? WHERE name = ?", "ss", $name, PocketMineApi::KEY_PROMOTED);
        Mysql::query("UPDATE spoon_prom SET value = ? WHERE name = ?", "ss", $name, PocketMineApi::KEY_LATEST);
        if($incompatible) {
            Mysql::query("UPDATE releases SET flags = flags | ?", "i", Release::FLAG_OUTDATED);
            Mysql::query("UPDATE spoon_prom SET value = ? WHERE name = ?", "ss", $name, PocketMineApi::KEY_PROMOTED_COMPAT);
            Mysql::query("UPDATE spoon_prom SET value = ? WHERE name = ?", "ss", $name, PocketMineApi::KEY_LATEST_COMPAT);
        } else {
            Mysql::query("UPDATE release_spoons SET till = ? WHERE till = ?", "ss", $name, $last);
        }

        return $id;
    }
}
