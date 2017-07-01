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

namespace poggit\release\submit\entry;

class DroplistSubmitFormEntry extends SubmitFormEntry {
    /** @var string[] <code>[option_name => display_name]</code> */
    public $options;

    public function __construct(string $inputId, string $displayName, string $remarks, array $options, $lastReleaseValue = null, $srcDetectedValue = null, bool $preferSrcDetected = SubmitFormEntry::PREFER_LAST_RELEASE_VALUE) {
        parent::__construct($inputId, $displayName, $remarks, $lastReleaseValue, $srcDetectedValue, $preferSrcDetected);
        $this->options = $options;
    }
}
