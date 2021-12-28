<?php

/*
 * Poggit
 *
 * Copyright (C) 2016-2018 Poggit
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

namespace poggit\ci\api;

use poggit\account\Session;
use poggit\module\AjaxModule;
use poggit\utils\internet\Mysql;
use function json_encode;

class ProjectSubToggleAjax extends AjaxModule {
    const LEVEL_NONE = 0;
    const LEVEL_DEV_BUILDS = 1;
    const LEVEL_DEV_AND_PR_BUILDS = 2;

    public static $LEVELS_TO_HUMAN = [
        ProjectSubToggleAjax::LEVEL_NONE => "Unsubscribed",
        ProjectSubToggleAjax::LEVEL_DEV_BUILDS => "Dev builds only",
        ProjectSubToggleAjax::LEVEL_DEV_AND_PR_BUILDS => "Dev builds and Pull Request builds",
    ];

    protected function impl() {
        $projectId = $this->param("projectId");
        $level = $this->param("level");
        Mysql::query(/** @lang MySQL */
            "INSERT INTO project_subs (projectId, userId, level) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE level = ?",
            "iiii", $projectId, Session::getInstance()->getUid(), $level, $level);
        echo json_encode(["success" => true]);
    }
}
