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

namespace poggit\home;

use poggit\account\Session;
use poggit\module\AjaxModule;
use poggit\utils\internet\Mysql;

class SessionBumpNotifAjax extends AjaxModule {
    public function getName(): string {
        return "session.bumpnotif";
    }

    protected function impl() {
        $_SESSION["poggit"]["github"]["last_notif"] = time();
        Mysql::query("UPDATE users SET lastNotif = CURRENT_TIMESTAMP WHERE uid = ?", "i", Session::getInstance(false)->getUid());
    }
}
