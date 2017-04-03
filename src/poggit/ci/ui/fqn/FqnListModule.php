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

namespace poggit\ci\ui\fqn;

use poggit\Poggit;
use poggit\module\Module;
use poggit\utils\internet\MysqlUtils;

class FqnListModule extends Module {
    public function getName(): string {
        return "fqn.txt";
    }

    public function getAllNames(): array {
        return ["fqn.txt", "fqn.yml"];
    }

    public function output() {
        if(Poggit::getModuleName() === "fqn.yml"){
            header("Content-Type: text/yaml"); // TODO: we need a better query than this piece of mess (very slow)
            $query = "
            SELECT
                CONCAT(n.name, '\\\\', kc.name) AS fqn,
                COUNT(DISTINCT p.projectId) AS projects,
                COUNT(DISTINCT b.buildId) AS builds,
                (SELECT CONCAT_WS('/', b2.created, 'https://github.com', r2.owner, r2.name, 'commit', b2.sha) FROM builds b2
                    JOIN projects p2 ON b2.projectId=p2.projectId JOIN repos r2 ON p2.repoId=r2.repoId
                    WHERE b2.created = MIN(b.created) LIMIT 1) AS first_commit_url,
                (SELECT CONCAT_WS('/', b2.created, 'https://github.com', r2.owner, r2.name, 'commit', b2.sha) FROM builds b2
                    JOIN projects p2 ON b2.projectId=p2.projectId JOIN repos r2 ON p2.repoId=r2.repoId
                    WHERE b2.created = MAX(b.created) LIMIT 1) AS last_commit_url,
                GROUP_CONCAT(DISTINCT CONCAT_WS('/', r.owner, r.name, p.name)) AS usages,
                MIN(CONCAT_WS('/', r.owner, r.name, p.name, CONCAT(IF(b.class = 1, 'dev', IF(b.class = 4, 'pr', '?')), ':', b.internal))) AS example
            FROM class_occurrences co
                JOIN builds b ON b.buildId=co.buildId
                JOIN projects p ON p.projectId=b.projectId
                JOIN repos r ON r.repoId=p.repoId
                JOIN known_classes kc ON kc.clid=co.clid
                JOIN namespaces n ON n.nsid=kc.parent
            GROUP BY fqn 
            ORDER BY projects DESC, builds DESC, UPPER(fqn) ASC";
            $rows = MysqlUtils::query($query);
            foreach($rows as &$row){
                $row["projects"] = (int) $row["projects"];
                $row["builds"] = (int) $row["builds"];
                $parts = explode("/", $row["first_commit_url"], 2);
                list($row["first_created"], $row["first_commit_url"]) = $parts;
                $parts = explode("/", $row["last_commit_url"], 2);
                list($row["last_created"], $row["last_commit_url"]) = $parts;
                $row["usages"] = explode(",", $row["usages"]);
            }
            echo "---\n";
            echo "# Below are a list of all classes ever declared in a non-syntax-error non-obfuscated PHP file built in Poggit-CI.\n";
            echo "# `fqn` is the fully-qualified class name.\n";
            echo "# `projects` is the number of projects using this class name. If this is greater than 1, pay attention -- they may conflict with each other.\n";
            echo "# `builds` is the number of builds using this class name. This is usually not useful.\n";
            echo "# `first_created`, `first_commit_url`, `last_created`, `last_commit_url` are the first and last commits' date and respective URLs that whose code declares this class name\n";
            echo "# `usages` are the projects that use this class name.";
            echo "# `example` is an example build that uses this project\n";
            echo substr(yaml_emit($rows), 3);
            return;
        }
        header("Content-Type: text/plain");
        $rows = MysqlUtils::query("SELECT DISTINCT CONCAT(ns.name, '\\\\', cl.name) AS fqn FROM known_classes cl LEFT JOIN namespaces ns ON cl.parent = ns.nsid WHERE ns.name IS NOT NULL ORDER BY fqn");
        echo "# Below is a list of classes that had once been declared in a non-syntax-error non-obfuscated PHP file built in Poggit-CI.\n";
        echo "# Visit https://poggit.pmmp.io/fqn for an interactive viewer\n";
        foreach($rows as $row) {
            echo $row["fqn"], "\n";
        }
    }
}
