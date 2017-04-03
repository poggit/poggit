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
        return ["fqn.txt", "fqn.table"];
    }

    public function output() {
        header("Content-Type: text/plain");
        if(Poggit::getModuleName() === "fqn.table"){
            $query = "
            SELECT
                CONCAT(n.name, '\\\\', kc.name) AS fqn,
                COUNT(DISTINCT p.projectId) AS projects,
                COUNT(DISTINCT b.buildId) AS builds,
                MIN(CONCAT_WS('/', r.owner, r.name, p.name, CONCAT(IF(b.class = 1, 'dev', IF(b.class = 4, 'pr', '?')), ':', b.internal))) AS eg,
                GROUP_CONCAT(DISTINCT CONCAT_WS('/', r.owner, r.name, p.name)) AS pn
            FROM class_occurrences co
                JOIN builds b ON b.buildId=co.buildId
                JOIN projects p ON p.projectId=b.projectId
                JOIN repos r ON r.repoId=p.repoId
                JOIN known_classes kc ON kc.clid=co.clid
                JOIN namespaces n ON n.nsid=kc.parent
            GROUP BY fqn 
            ORDER BY projects DESC, builds DESC, UPPER(fqn) ASC";
            $rows = MysqlUtils::query($query);
            foreach($rows as $row){
                foreach($row as $k => $v){
                    if(!isset($cols[$k])) $cols[$k] = [$k];
                    $cols[$k][] = $v;
                }
            }
            $paddings = [];
			foreach($cols as $k => $v){
				$paddings[$k] = max(array_map("strlen", $v));
			}
			$len = array_sum($paddings) + 1 + count($cols) * 3;
			for($i = 0; $i <= $rows; $i++){
				if($i === 0) echo str_repeat("=", $len), PHP_EOL;
				foreach($cols as $k => $v){
					echo "| " . str_pad($v[$i], $paddings[$k], " ", STR_PAD_RIGHT) . " ";
				}
				echo "|", PHP_EOL;
				if($i === 0) echo str_repeat("=", $len), PHP_EOL;
			}
			echo str_repeat("=", $len), PHP_EOL;
            return;
        }
        $rows = MysqlUtils::query("SELECT DISTINCT CONCAT(ns.name, '\\\\', cl.name) AS fqn FROM known_classes cl LEFT JOIN namespaces ns ON cl.parent = ns.nsid WHERE ns.name IS NOT NULL ORDER BY fqn");
        echo "# Below is a list of classes that had once been declared in a non-syntax-error non-obfuscated PHP file built in Poggit-CI.\n";
        echo "# Visit https://poggit.pmmp.io/fqn for an interactive viewer\n";
        foreach($rows as $row) {
            echo $row["fqn"], "\n";
        }
    }
}
