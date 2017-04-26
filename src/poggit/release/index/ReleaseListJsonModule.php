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

namespace poggit\release\index;

use poggit\module\Module;
use poggit\Poggit;
use poggit\release\PluginRelease;
use poggit\utils\internet\MysqlUtils;

class ReleaseListJsonModule extends Module {
    public function getName(): string {
        return "releases.json";
    }

    public function getAllNames(): array {
        return ["releases.json", "plugins.json", "releases.min.json", "plugins.min.json", "releases.list"];
    }

    public function output() {
        header("Content-Type: application/json");

        $where = "WHERE state >= 3";
        $types = "";
        $args = [];
        if(isset($_REQUEST["id"])) {
            $where .= " AND r.releaseId = ?";
            $types = "i";
            $args[] = (int) $_REQUEST["id"];
        } elseif(isset($_REQUEST["name"])) {
            $where .= " AND r.name = ?";
            $types = "s";
            $args[] = $_REQUEST["name"];
            if(isset($_REQUEST["version"])) {
                $where .= " AND r.version = ?";
                $types .= "s";
                $args[] = $_REQUEST["version"];
            }
        }
        $data = MysqlUtils::query("SELECT 
            releaseId AS id,
            r.name,
            r.version,
            CONCAT('https://poggit.pmmp.io/p/', r.name, '/', r.version) AS html_url,
            r.shortDesc AS tagline,
            CONCAT('https://poggit.pmmp.io/r/', r.artifact) AS artifact_url,
            art.dlCount AS downloads,
            repos.repoId AS repo_id,
            CONCAT(repos.owner, '/', repos.name) AS repo_name,
            p.projectId AS project_id,
            p.name AS project_name,
            b.buildId AS build_id,
            b.internal AS build_number,
            b.sha AS build_commit,
            CONCAT('https://poggit.pmmp.io/r/', r.description) AS description_url,
            icon AS icon_url,
            IF(changelog = 1, NULL, CONCAT('https://poggit.pmmp.io/r/', r.changelog)) AS changelog_url,
            r.license,
            IF(license = 'custom', CONCAT('https://poggit.pmmp.io/r/', r.licenseRes), NULL) AS license_url,
            (r.flags & 2) > 0 AS is_pre_release,
            (r.flags & 4) > 0 AS is_outdated,
            (r.flags & 8) > 0 AS is_official,
            UNIX_TIMESTAMP(r.creation) AS submission_date,
            r.state,
            UNIX_TIMESTAMP(r.updateTime) AS last_state_change_date,
            (SELECT group_concat(category ORDER BY isMainCategory DESC SEPARATOR ',') FROM release_categories rc WHERE rc.projectId = r.projectId) AS categories,
            (SELECT group_concat(word SEPARATOR ',') FROM release_keywords rw WHERE rw.projectId = r.projectId) AS keywords,
            (SELECT group_concat(CONCAT(since, ',', till) SEPARATOR ';') FROM release_spoons rs WHERE rs.releaseId = r.releaseId) AS api,
            (SELECT group_concat(CONCAT(name, ':', version, ':', depRelId, ':', IF(isHard, '1', '0')) SEPARATOR ';') FROM release_deps rd WHERE rd.releaseId = r.releaseId) AS deps
            FROM releases r
                INNER JOIN builds b ON r.buildId = b.buildId
                INNER JOIN projects p ON r.projectId = p.projectId
                INNER JOIN repos ON p.repoId = repos.repoId
                INNER JOIN resources art ON art.resourceId = r.artifact
            $where
            ORDER BY p.name, r.projectId ASC, r.creation DESC
            ", $types, ...$args);

        foreach($data as &$row) {
            foreach(["id", "downloads", "repo_id", "project_id", "build_id", "build_number", "submission_date", "state", "last_state_change_date"] as $col) {
                $row[$col] = (int) $row[$col];
            }
            foreach(["is_pre_release", "is_outdated", "is_official"] as $col) {
                $row[$col] = (bool) (int) $col;
            }
            $row["state_name"] = PluginRelease::$STATE_ID_TO_HUMAN[$row["state"]];
            $row["categories"] = array_map(function ($cat) {
                return [
                    "major" => false,
                    "category_name" => PluginRelease::$CATEGORIES[$cat]
                ];
            }, array_filter(array_unique(explode(",", $row["categories"] ?? ""))));
            if(count($row["categories"]) > 0) $row["categories"][0]["major"] = true;
            $row["keywords"] = array_unique(array_filter(explode(",", $row["keywords"] ?? "")));
            $row["api"] = array_map(function ($range) {
                list($from, $to) = explode(",", $range, 2);
                return ["from" => $from, "to" => $to];
            }, array_filter(explode(";", $row["api"] ?? "")));
            $row["deps"] = array_map(function ($dep) {
                list($name, $version, $depRelId, $isHard) = explode(":", $dep);
                return [
                    "name" => $name,
                    "version" => $version,
                    "depRelId" => $depRelId === "0" ? null : (int) $depRelId,
                    "isHard" => (bool) (int) $isHard
                ];
            }, array_filter(explode(";", $row["deps"] ?? "")));
        }

        $isMin = substr(Poggit::getModuleName(), -9) === ".min.json";
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_BIGINT_AS_STRING | JSON_UNESCAPED_SLASHES);
    }
}
