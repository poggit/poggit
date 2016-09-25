<?php

/*
 * pogit
 *
 * Copyright (C) 2016
 */

namespace poggit\resource;

class ResourceExpiredException extends ResourceNotFoundException {
    /** @var int */
    private $expiredTime;

    public function __construct(int $id, int $expiredTime) {
        parent::__construct($id);
        $this->expiredTime = $expiredTime;
        $this->message = "Resource #$id has been expired for $expiredTime seconds";
    }

    public function getExpiredTime() {
        return $this->expiredTime;
    }
}
