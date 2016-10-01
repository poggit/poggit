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

namespace poggit\resource;

use poggit\Poggit;
use const poggit\RESOURCE_DIR;

/**
 * Note: res and resource are different. res is the editable /res/ directory,
 * and resource is the string in a data file also tracked in the `resources` table in the database.
 */
class ResourceManager {
    const NULL_RESOURCE = 1;

    public static function getInstance() : ResourceManager {
        global $resourceMgr;
        if(!isset($resourceMgr)) {
            $resourceMgr = new ResourceManager();
        }
        return $resourceMgr;
    }

    private $resourceCache;

    public function __construct() {
        if(!is_dir(RESOURCE_DIR)) {
            mkdir(RESOURCE_DIR, 0777, true);
        }
    }

    public function getResource(int $id, string $type) : string {
        if($id === self::NULL_RESOURCE) {
            touch(RESOURCE_DIR . $id);
            return RESOURCE_DIR . $id;
        }
        if(!isset($this->resourceCache[$id])) {
            if(!$type) {
                $row = Poggit::queryAndFetch("SELECT type, unix_timestamp(created) + duration - unix_timestamp() AS remain FROM resources WHERE resourceId = $id");
                if(!isset($row[0])) {
                    throw new ResourceNotFoundException($id);
                }
                $remain = (int) $row[0]["remain"];
                if($remain <= 0) {
                    throw new ResourceExpiredException($id, -$remain);
                }
                $type = $row[0]["type"];
            }
            $this->resourceCache[$id] = $type;
        }
        $result = RESOURCE_DIR . $id . "." . $this->resourceCache[$id];
        if(!file_exists($result)) {
            throw new ResourceNotFoundException($id);
        }
        return $result;
    }

    public function createResource(string $type, string $mimeType, array $accessFilters = [], int $expiry = 315360000, &$id = null) : string {
        $id = Poggit::queryAndFetch("INSERT INTO resources (type, mimeType, accessFilters, duration) VALUES (?, ?, ?, ?)", "sssi", $type, $mimeType, json_encode($accessFilters, JSON_UNESCAPED_SLASHES), $expiry)->insert_id;
        return RESOURCE_DIR . $id . "." . $type;
    }
}
