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

namespace poggit\resource;

use poggit\account\Session;
use poggit\release\SubmitException;
use poggit\utils\internet\GitHub;
use poggit\utils\internet\Mysql;
use function dirname;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function is_dir;
use function json_encode;
use function mkdir;
use function strlen;
use const JSON_UNESCAPED_SLASHES;
use const poggit\RESOURCE_DIR;

/**
 * Note: res and resource are different. res is the editable /res/ directory,
 * and resource is the string in a data file also tracked in the `resources` table in the database.
 */
class ResourceManager {
    const NULL_RESOURCE = 1;

    public static function getInstance(): ResourceManager {
        global $resourceMgr;
        if(!isset($resourceMgr)) {
            $resourceMgr = new self();
        }
        return $resourceMgr;
    }

    private $resourceCache = [];

    public function storeArticle(string $type, string $text, string $context = null, string $src = null): int {
        switch($type) {
            case "txt":
                $path = $this->createResource("txt", "text/plain", [], $resourceId, 315360000, $src, strlen($text));
                file_put_contents($path, $text);
                return $resourceId;
            case "gfm":
            case "sm":
                $relMd = Mysql::query("INSERT INTO resources (type, mimeType, duration, src, fileSize, accessFilters) VALUES (?, ?, ?, ?, ?, '[]')",
                    "ssisi", "md", "text/markdown", 315360000, "{$src}.relmd", strlen($text))->insert_id;
                $relMdPath = self::pathTo($relMd, "md");
                file_put_contents($relMdPath, $text);

                $html = GitHub::ghApiPost("markdown", [
                    "text" => $text,
                    "mode" => $type === "gfm" ? "gfm" : "markdown",
                    "context" => $context
                ], Session::getInstance()->getAccessToken(), true);
                $resourceId = Mysql::query("INSERT INTO resources (type, mimeType, duration, relMd, src, fileSize, accessFilters) VALUES (?, ?, ?, ?, ?, ?, '[]')",
                    "ssiisi", "html", "text/html", 315360000, $relMd, $src, strlen($html))->insert_id;

                $htmlPath = self::pathTo($resourceId, "html");
                file_put_contents($htmlPath, $html);
                return $resourceId;
            default:
                throw new SubmitException("Unknown type $type");
        }
    }

    /**
     * @param int    $id
     * @param string $type
     * @param string $detectedType
     * @return string file path to resource
     */
    public function getResource(int $id, string $type = "", string &$detectedType = ""): string {
        if($id === self::NULL_RESOURCE) throw new ResourceNotFoundException($id);
        if(!isset($this->resourceCache[$id])) {
            if($type === "") {
                $row = Mysql::query("SELECT type, unix_timestamp(created) + duration - unix_timestamp() AS remain FROM resources WHERE resourceId = $id");
                if(!isset($row[0])) throw new ResourceNotFoundException($id);
                $remain = (int) $row[0]["remain"];
                if($remain <= 0) throw new ResourceExpiredException($id, -$remain);
                $type = $row[0]["type"];
            }
            $this->resourceCache[$id] = $type;
        }
        $result = self::pathTo($id, $detectedType = $this->resourceCache[$id]);
        if(!file_exists($result)) throw new ResourceNotFoundException($id);
        return $result;
    }

    public function createResource(string $type, string $mimeType, array $accessFilters, &$id, int $expiry, string $src, int $fileSize): string {
        $id = Mysql::query("INSERT INTO resources (type, mimeType, accessFilters, duration, src, fileSize) VALUES (?, ?, ?, ?, ?, ?)",
            "sssisi", $type, $mimeType, json_encode($accessFilters, JSON_UNESCAPED_SLASHES), $expiry, $src, $fileSize)->insert_id;
        return self::pathTo($id, $type);
    }

    public static function pathTo(int $rsrId, string $type): string {
        $kilo = (int) ($rsrId / 1000);
        $ret = RESOURCE_DIR . $kilo . "/" . $rsrId . "." . $type;
        if(!is_dir(dirname($ret))) {
            mkdir(dirname($ret), 0777, true);
        }
        return $ret;
    }

    public static function read(int $rsrId, string $type): string {
        return file_get_contents(self::pathTo($rsrId, $type));
    }
}
