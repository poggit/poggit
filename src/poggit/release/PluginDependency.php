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

namespace poggit\release;

class PluginDependency {
    /** @var string */
    public $name;
    /** @var string */
    public $version;
    /** @var int|null */
    public $dependencyReleaseId;
    /** @var bool */
    public $isHard;

    public function __construct(string $name, string $version, int $dependencyReleaseId, bool $isHard) {
        $this->name = $name;
        $this->version = $version;
        $this->dependencyReleaseId = $dependencyReleaseId;
        $this->isHard = $isHard;
    }
}
