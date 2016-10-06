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

class MainClassMissingStatus extends BuildStatus {
    public $shouldFile = null;

    public function __construct($shouldFile) {
        parent::__construct(self::STATUS_ERR);
        $this->shouldFile = $shouldFile;
    }

    public function toString() : string {
        if($this->shouldFile === "plugin.yml") {
            return "Attribute 'main' missing in plugin.yml or is not a valid class name";
        }
        return "Main class file $this->shouldFile missing";
    }
}
