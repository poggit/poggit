<?php

/*
 * Copyright 2016 poggit
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

namespace poggit\page\build;

use poggit\page\Page;
use poggit\Poggit;

class BuildPage extends Page {
    public function getName() : string {
        return "build";
    }

    public function output() {
        $parts = array_filter(explode("/", $this->getQuery()));
        if(count($parts) === 0) {
            $parts = [$_SESSION["name"]];
        }
        if(count($parts) === 1) {
            $this->displayAccount($parts);
        } elseif(count($parts) === 2) {
            $this->displayRepo($parts);
        } else {
            $this->displayProject($parts);
        }
    }

    /**
     * @param string[] $parts
     */
    public function displayAccount(array $parts) {
        list($login) = $parts;
        $rows = Poggit::queryAndFetch("SELECT repoId, owner, name, private,
            (SELECT COUNT(*) FROM builds WHERE builds.projectId = projects.projectId) AS builds,
            (SELECT COUNT(*) FROM ) FROM repos WHERE owner=? AND build=1", "s", $login);

    }

    /**
     * @param string[] $parts
     */
    public function displayRepo(array $parts) {
        list($login, $repo) = $parts;

    }

    /**
     * @param string[] $parts
     */
    public function displayProject(array $parts) {
        list($login, $repo, $proj) = $parts;

    }
}
