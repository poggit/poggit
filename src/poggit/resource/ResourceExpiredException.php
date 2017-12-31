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
