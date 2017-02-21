<?php

/*
 * Poggit
 *
 * Copyright (C) 2016-2017 Poggit
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

namespace poggit\module\build;

use poggit\builder\ProjectBuilder;
use poggit\module\Module;
use poggit\Poggit;
use poggit\utils\internet\CurlUtils;
use poggit\utils\internet\GitHubAPIException;
use poggit\utils\internet\MysqlUtils;
use poggit\utils\SessionUtils;

class FqnListModule extends Module {
    public function getName(): string {
        return "fqn.txt";
    }

    public function output() {
        $rows = MysqlUtils::query("SELECT DISTINCT CONCAT(ns.name, '\\\\', cl.name) AS fqn FROM known_classes cl LEFT JOIN namespaces ns ON cl.parent = ns.nsid WHERE ns.name IS NOT NULL ORDER BY fqn");
        header("Content-Type: text/plain");
        echo "# Below is a list of classes that had once been declared in a non-syntax-error non-obfuscated PHP file built in Poggit-CI.\n";
        echo "# Visit https://poggit.pmmp.io/fqn for an interactive viewer\n";
        foreach($rows as $row) echo $row["fqn"], "\n";
    }
}
