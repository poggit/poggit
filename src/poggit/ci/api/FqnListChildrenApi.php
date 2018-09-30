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

namespace poggit\ci\api;

use poggit\module\Module;
use poggit\utils\internet\Mysql;
use function json_encode;
use const JSON_PRETTY_PRINT;

class FqnListChildrenApi extends Module {
    public function output() {
        $result = [];
        if(isset($_REQUEST["parent"])) {
            $parent = (int) $_REQUEST["parent"];
            foreach(Mysql::query("SELECT nsid, name FROM namespaces WHERE parent = ?", "i", $parent) as $row) {
                $result[] = [
                    "type" => "ns",
                    "id" => (int) $row["nsid"],
                    "name" => $row["name"]
                ];
            }
            foreach(Mysql::query("SELECT known_classes.clid, name,
                    COUNT(DISTINCT class_occurrences.buildId) builds,
                    COUNT(DISTINCT builds.projectId) projects
                FROM known_classes
                    LEFT JOIN class_occurrences ON class_occurrences.clid = known_classes.clid
                    LEFT JOIN builds ON builds.buildId = class_occurrences.buildId
                WHERE known_classes.parent = ?
                GROUP BY known_classes.clid", "i", $parent) as $row) {
                $result[] = [
                    "type" => "class",
                    "id" => (int) $row["clid"],
                    "name" => $row["name"],
                    "projects" => (int) $row["projects"],
                    "builds" => (int) $row["builds"],
                ];
            }
        } else {
            foreach(Mysql::query("SELECT nsid, name FROM namespaces WHERE parent IS NULL") as $row) {
                $result[] = [
                    "type" => "ns",
                    "id" => (int) $row["nsid"],
                    "name" => $row["name"]
                ];
            }
            foreach(Mysql::query("SELECT known_classes.clid, name,
                    COUNT(DISTINCT class_occurrences.buildId) builds,
                    COUNT(DISTINCT builds.projectId) projects
                FROM known_classes
                    LEFT JOIN class_occurrences ON class_occurrences.clid = known_classes.clid
                    LEFT JOIN builds ON builds.buildId = class_occurrences.buildId
                WHERE known_classes.parent IS NULL
                GROUP BY known_classes.clid") as $row) {
                $result[] = [
                    "type" => "class",
                    "id" => (int) $row["clid"],
                    "name" => $row["name"],
                    "projects" => (int) $row["projects"],
                    "builds" => (int) $row["builds"],
                ];
            }
        }
        echo json_encode($result, JSON_PRETTY_PRINT);
    }
}
