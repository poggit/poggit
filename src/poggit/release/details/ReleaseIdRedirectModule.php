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

namespace poggit\release\details;

use poggit\account\Session;
use poggit\Config;
use poggit\Meta;
use poggit\module\Module;
use poggit\release\Release;
use poggit\utils\internet\GitHub;
use poggit\utils\internet\Mysql;

class ReleaseIdRedirectModule extends Module {
    public function output() {
        $releaseId = (int) $this->getQuery();
        if($releaseId === 0) {
            $this->errorBadRequest("Correct usage: /rid/:release_id");
        }

        $rows = Mysql::query("SELECT releases.name, version, state, repoId FROM releases
            INNER JOIN projects on releases.projectId = projects.projectId
            WHERE releaseId = ?", "i", $releaseId);
        if(!isset($rows[0])) $this->errorNotFound();

        $session = Session::getInstance();
        $state = (int) $rows[0]["state"];
        $repoId = (int) $rows[0]["repoId"];
        if($state < Config::MIN_PUBLIC_RELEASE_STATE && !$session->isLoggedIn()) $this->errorNotFound();

        if($state < Release::STATE_CHECKED) {
            if(Meta::getAdmlv() < Meta::ADMLV_REVIEWER) {
                if(!GitHub::testPermission($repoId, $session->getAccessToken(), $session->getName(), "push")) {
                    $this->errorNotFound();
                }
            }
        }

        Meta::redirect("p/" . $rows[0]["name"] . "/" . $rows[0]["version"]);
    }
}
