<?php

/*
 * pogit
 *
 * Copyright (C) 2016
 */

namespace poggit\resource;

use poggit\Poggit;
use const poggit\RESOURCE_DIR;

/**
 * Note: res and resource are different. res is the editable /res/ directory,
 * and resource is the string in a data file also tracked in the `resources` table in the database.
 */
class ResourceManager {
    private $resourceCache;

    public function __construct() {
        if(!is_dir(RESOURCE_DIR)) {
            mkdir(RESOURCE_DIR, 0777, true);
        }
    }

    public function getResource(int $id, string $type) : string {
        if(!isset($this->resourceCache[$id])) {
            if(!isset($type)) {
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
    }

    public function createResource(string $type, int $expiry = 315360000, &$id = null) : string {
        $id = Poggit::queryAndFetch("INSERT INTO resources (type, duration) VALUES (?, ?)", "si", $type, $expiry)->insert_id;
        return RESOURCE_DIR . $id . "." . $type;
    }
}
