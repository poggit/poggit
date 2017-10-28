<?php

/*
 *
 * poggit
 *
 * Copyright (C) 2017 SOFe
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 */

declare(strict_types=1);

namespace poggit\account;

use poggit\Meta;
use poggit\module\AjaxModule;
use poggit\utils\internet\Mysql;

class KeepOnlineAjax extends AjaxModule {
    public function getName(): string {
        return "session.online";
    }

    protected function impl() {
        Mysql::query("INSERT INTO user_ips (uid, ip) VALUES (?, ?) ON DUPLICATE KEY UPDATE time = CURRENT_TIMESTAMP", "is", Session::getInstance()->getUid(), Meta::getClientIP());

        echo (int) Mysql::query(/** @lang MySQL */
            "SELECT KeepOnline(?, ?) onlineCount", "si",
            Meta::getClientIP(), Session::getInstance()->getUid())[0]["onlineCount"];
    }

    protected function needLogin(): bool {
        return false;
    }
}
