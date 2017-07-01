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

abstract class SubmitFormEntry {
    const PREFER_SRC_DETECTED_VALUE = true;
    const PREFER_LAST_RELEASE_VALUE = false;

    /** @var string */
    public $inputId; // for hybrid entries, the input IDs are "{$inputId}-textval" and "{$inputId}-typesel" respectively
    /** @var string */
    public $displayName;
    /** @var string */
    public $remarks;

    public $lastReleaseValue;
    public $srcDetectedValue;
    /** @var bool */
    public $preferSrcDetected;

    /**
     * SubmitFormEntry constructor.
     *
     * @param string     $inputId
     * @param string     $displayName
     * @param string     $remarks
     * @param mixed|null $lastReleaseValue
     * @param mixed|null $srcDetectedValue
     * @param bool       $preferSrcDetected
     */
    public function __construct(string $inputId, string $displayName, string $remarks, $lastReleaseValue = null, $srcDetectedValue = null, bool $preferSrcDetected = SubmitFormEntry::PREFER_LAST_RELEASE_VALUE) {
        $this->lastReleaseValue = $lastReleaseValue;
        $this->srcDetectedValue = $srcDetectedValue;
        $this->preferSrcDetected = $preferSrcDetected;
        $this->inputId = $inputId;
        $this->displayName = $displayName;
        $this->remarks = $remarks;
    }

}
