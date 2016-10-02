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

namespace poggit\page\webhooks\buildstatus;

class BadPracticeStatus extends BuildStatus{
    const CLOSING_TAG = "Closing tag in PHP file";
    const INLINE_HTML = "Use of inline HTML";
    const MULTI_CLASS_FILE = "Multiple class declarations in a file";
    const PSR_MISMATCH = "Class path does not follow PSR-4 convention";

    /** @var string */
    private $type;
    /** @var string */
    private $file;
    /** @var int */
    private $line;

    public function __construct(int $status, string $type, string $file, int $line) {
        parent::__construct($status);
        $this->type = $type;
        $this->file = $file;
        $this->line = $line;
    }
}
