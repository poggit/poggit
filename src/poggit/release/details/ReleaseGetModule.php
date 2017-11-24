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

namespace poggit\release\details;

use poggit\Meta;
use poggit\module\Module;
use poggit\release\Release;
use poggit\utils\internet\Mysql;
use poggit\utils\lang\Lang;
use poggit\utils\PocketMineApi;

class ReleaseGetModule extends Module {
    public function getName(): string {
        return "get";
    }

    public function getAllNames(): array {
        return ["get", "get.md5", "get.sha1"];
    }

    public function output() {
        $input = $this->getQuery();
        $parts = Lang::explodeNoEmpty("/", $input, 3);
        if(!isset($parts[0])) $this->errorBadRequest("Usage: /get/{name}/{version}[/{anything you like}]");
        $name = $parts[0];
        $version = $parts[1] ?? "~";
        $dlName = $parts[2] ?? null;
        $preRelease = isset($_REQUEST["prerelease"]) && $_REQUEST["prerelease"] !== "off" && $_REQUEST["prerelease"] !== "no";
        $minState = max(Release::STATE_CHECKED, // ensure submitted/draft/rejected releases cannot be fetched
            Release::$STATE_SID_TO_ID[$_REQUEST["state"] ?? "checked"] ?? Release::STATE_CHECKED);
        if(isset($_REQUEST["api"])) {
            $apiVersions = array_flip(array_keys(PocketMineApi::$VERSIONS));
            if(isset($apiVersions[$_REQUEST["api"]])) {
                $requiredApi = $apiVersions[$_REQUEST["api"]];
            } else {
                $this->errorBadRequest("Unknown API " . $_REQUEST["api"]);
            }
        }

        $query = "SELECT r.artifact, r.version, r.flags, r.state,
                UNIX_TIMESTAMP(r.creation) created, UNIX_TIMESTAMP(r.updateTime) stateChange,
                GROUP_CONCAT(DISTINCT concat_ws(',', rs.since, rs.till) SEPARATOR '/') spoons
                FROM release_spoons rs RIGHT JOIN releases r ON rs.releaseId = r.releaseId
                WHERE r.name = ? AND r.state >= ?";
        $types = "si";
        $args = [$name, $minState];
        if($version !== "~") {
            $query .= " AND version = ?";
            $types .= "s";
            $args[] = $version;
        }
        if(!$preRelease) {
            $query .= " AND (flags & ?) = 0";
            $types .= "i";
            $args[] = Release::FLAG_PRE_RELEASE;
        }
        $query .= " GROUP BY r.releaseId";
        $query .= " ORDER BY creation DESC";

        $rows = Mysql::query($query, $types, ...$args);

        // loop_rows:
        foreach($rows as $row) {
            $a = $row["artifact"];
            $v = $row["version"];
            $s = $row["spoons"];
            $state = (int) $row["state"];
            $created = (int) $row["created"];
            $stateChange = (int) $row["stateChange"];
            $flags = (int) $row["flags"];
            if(isset($requiredApi, $apiVersions)) {
                foreach(explode("/", $s) as $spoon) {
                    list($from, $till) = explode(",", $spoon);
                    $from = $apiVersions[$from]; // let it error if index is undefined
                    $till = $apiVersions[$till]; // let it error if index is undefined
                    if($from <= $requiredApi && $requiredApi <= $till) {
                        $ok = true;
                        break;
                    }
                }
                if(!isset($ok)) {
                    continue; // loop_rows
                }
            }
            $suffix = substr(Meta::getModuleName(), 3);
            header("X-Poggit-Resolved-Version: $v");
            header("X-Poggit-Resolved-Release-Date: " . date(DATE_ATOM, $created));
            header("X-Poggit-Resolved-State-Change-Date: " . date(DATE_ATOM, $stateChange));
            header("X-Poggit-Resolved-Is-Prerelease: " . (($flags & Release::FLAG_PRE_RELEASE) > 0 ? "true" : "false"));
            header("X-Poggit-Resolved-State: " . Release::$STATE_ID_TO_HUMAN[$state]);
            Meta::redirect("r{$suffix}/$a/" . ($dlName ?? ($name . "_v" . $v . ".phar")));
            break;
        }

        http_response_code(404);
        header("Content-Type:text/plain");
        echo "No release found matching these conditions:\n";
        echo "- Name = $name\n";
        if($version !== "~") echo "- Version = $version\n";
        echo "- State >= " . Release::$STATE_ID_TO_HUMAN[$minState] . "\n";
        if(isset($_REQUEST["api"])) {
            echo "- Supports API version " . $_REQUEST["api"] . "\n";
        }
        if(!$preRelease) {
            echo "- Not a pre-release\n";
        }
    }
}
