<?php

/*
 * pogit
 *
 * Copyright (C) 2016
 */

namespace poggit\resource;

use MongoDB\Driver\Exception\RuntimeException;

class ResourceNotFoundException extends RuntimeException {
    /** @var int */
    private $resourceId;

    public function __construct(int $resourceId) {
        parent::__construct();
        $this->message = "Resource #" . $resourceId . " cannot be found";
        $this->resourceId = $resourceId;
    }

    public function getResourceId() {
        return $this->resourceId;
    }
}
