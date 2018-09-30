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

namespace poggit\ci\ui\fqn;

use poggit\ci\builder\ProjectBuilder;
use poggit\Meta;
use poggit\module\Module;
use poggit\utils\internet\Mysql;
use function apcu_exists;
use function apcu_fetch;
use function apcu_store;
use function explode;
use function header;
use function substr;
use function yaml_emit;

class FqnListModule extends Module {
    public function output() {
        if(Meta::getModuleName() === "fqn.yml") {
            $noUsage = isset($_REQUEST["nousage"]) ? 1 : 0;
            header("Content-Type: text/yaml");
            header("Cache-Control: public, max-age=3600");
            $apcuKey = "poggit.FqnList.yml." . ($noUsage ? "n" : "y");
            if(apcu_exists($apcuKey)) {
                echo apcu_fetch($apcuKey);
                return;
            }
            // TODO: we need a better query than this piece of mess (very slow)
            $query = "SELECT
                fqn, projects, builds, usages,
                r1.owner AS first_repo_owner, r1.name AS first_repo_name, p1.name AS first_project_name, b1.buildId AS first_build,
                    b1.class AS first_class, b1.internal AS first_internal, b1.sha AS first_commit, b1.created AS first_date,
                r2.owner AS last_repo_owner, r2.name AS last_repo_name, p1.name AS last_project_name, b2.buildId AS last_build,
                    b2.class AS last_class, b2.internal AS last_internal, b2.sha AS last_commit, b2.created AS last_date
            FROM (SELECT
                    CONCAT(n.name, '\\\\', kc.name) AS fqn,
                    COUNT(DISTINCT p.projectId) AS projects,
                    COUNT(DISTINCT b.buildId) AS builds,
                    MIN(b.buildId) AS minBuild,
                    MAX(b.buildId) AS maxBuild,
                    IF($noUsage, '', GROUP_CONCAT(DISTINCT CONCAT_WS('/', r.owner, r.name, p.name))) AS usages
                FROM class_occurrences co
                    JOIN builds b ON b.buildId=co.buildId
                    JOIN projects p ON p.projectId=b.projectId
                    JOIN repos r ON r.repoId=p.repoId
                    JOIN known_classes kc ON kc.clid=co.clid
                    JOIN namespaces n ON n.nsid=kc.parent
                GROUP BY fqn ORDER BY projects DESC, UPPER(fqn)
            ) t
                JOIN builds b1 ON t.minBuild = b1.buildId
                JOIN projects p1 ON b1.projectId = p1.projectId
                JOIN repos r1 ON p1.repoId = r1.repoId
                JOIN builds b2 ON t.maxBuild = b2.buildId
                JOIN projects p2 ON b2.projectId = p2.projectId
                JOIN repos r2 ON p2.repoId = r2.repoId";
            $rows = Mysql::query($query);
            $output = [];
            foreach($rows as $row) {
                $row["projects"] = (int) $row["projects"];
                $row["builds"] = (int) $row["builds"];
                if($noUsage) {
                    unset($row["usages"]);
                } else $row["usages"] = explode(",", $row["usages"]);
                $row["firstBuild"] = [
                    "repo" => ["owner" => $row["first_repo_owner"], "name" => $row["first_repo_name"]],
                    "project" => $row["first_project_name"],
                    "id" => (int) $row["first_build"],
                    "number" => ProjectBuilder::$BUILD_CLASS_SID[$row["first_class"]] . "/" . (int) $row["first_internal"],
                    "sha" => $row["first_commit"],
                    "created" => $row["first_date"]
                ];
                $row["lastBuild"] = [
                    "repo" => ["owner" => $row["last_repo_owner"], "name" => $row["last_repo_name"]],
                    "project" => $row["last_project_name"],
                    "id" => (int) $row["last_build"],
                    "number" => ProjectBuilder::$BUILD_CLASS_SID[$row["last_class"]] . "/" . (int) $row["last_internal"],
                    "sha" => $row["last_commit"],
                    "created" => $row["last_date"]
                ];
                if($row["firstBuild"]["id"] === $row["lastBuild"]["id"]) {
                    unset($row["lastBuild"]);
                } elseif($row["firstBuild"]["repo"] === $row["lastBuild"]["repo"]) {
                    unset($row["lastBuild"]["repo"]);
                    if($row["firstBuild"]["project"] === $row["lastBuild"]["project"]) {
                        unset($row["lastBuild"]["project"]);
                    }
                }
                unset($row["first_repo_owner"], $row["first_repo_name"], $row["first_project_name"], $row["first_build"],
                    $row["first_class"], $row["first_internal"], $row["first_commit"], $row["first_date"],
                    $row["last_repo_owner"], $row["last_repo_name"], $row["last_project_name"], $row["last_build"],
                    $row["last_class"], $row["last_internal"], $row["last_commit"], $row["last_date"]
                );
                $fqn = $row["fqn"];
                unset($row["fqn"]);
                $output[$fqn] = $row;
            }

            $output = "---\n" .
                "# Below are a list of all classes ever declared in a non-syntax-error non-obfuscated PHP file built in Poggit-CI.\n" .
                "# The keys are the fully-qualified class name.\n" .
                "# `projects` is the number of projects using this class name. If this is greater than 1, pay attention -- they may conflict with each other.\n" .
                "# `builds` is the number of builds using this class name. This is usually not useful.\n" .
                "# `usages` are the projects that use this class name. Add the ?nousage parameter if this field is not desired.\n" .
                "# `firstBuild` and `lastBuild` shows the respective information of the first and last builds that declare this class name.\n" .
                "#     Some values may be omitted in `lastBuild` if they are the same as those in `firstBuild`, or even the whole object removed if identical.\n" .
                "# This list may be cached for up to one hour.\n" .
                substr(yaml_emit($output), 3);
            echo $output;
            apcu_store($apcuKey, $output, 3600);
            return;
        }
        header("Content-Type: text/plain");
        $rows = Mysql::query("SELECT DISTINCT CONCAT(ns.name, '\\\\', cl.name) AS fqn FROM known_classes cl LEFT JOIN namespaces ns ON cl.parent = ns.nsid WHERE ns.name IS NOT NULL ORDER BY fqn");
        echo "# Below is a list of classes that had once been declared in a non-syntax-error non-obfuscated PHP file built in Poggit-CI.\n";
        echo "# Visit https://poggit.pmmp.io/fqn for an interactive viewer\n";
        foreach($rows as $row) {
            echo $row["fqn"], "\n";
        }
    }
}
