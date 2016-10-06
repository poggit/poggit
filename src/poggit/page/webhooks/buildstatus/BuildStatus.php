<?php

/*
 * Poggit
 *
 * Copyright (C) 2016 Poggit
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

namespace poggit\page\webhooks\buildstatus;

class BuildStatus {
    const STATUS_GOOD = 0;
    const STATUS_NEUTRAL = 1;
    const STATUS_LINT = 2;
    const STATUS_WARN = 3;
    const STATUS_ERR = 4;

    public static $STATUS_HUMAN = [
        self::STATUS_GOOD => "good",
        self::STATUS_NEUTRAL => "neutral",
        self::STATUS_LINT => "lint",
        self::STATUS_WARN => "warn",
        self::STATUS_ERR => "error",
    ];

    public $name;
    public $status;

    public function __construct(int $status) {
        $this->name = (new \ReflectionClass($this))->getShortName();
        $this->status = $status;
    }
}
