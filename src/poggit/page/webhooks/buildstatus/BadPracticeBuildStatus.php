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

class BadPracticeBuildStatus extends BuildStatus {
    const CLOSING_TAG = "CLOSING_TAG";
    const INLINE_HTML = "INLINE_HTML";
    const MULTI_CLASS_FILE = "MULTI_CLASS_FILE";

    /** @var string */
    public $type;
    /** @var string */
    public $file;
    /** @var int */
    public $line;

    protected function echoString() {
        ?>
        Bad practice in code in file <?= htmlspecialchars($this->file) ?> of line <?= $this->line ?>:
        <?php
        switch($this->type) {
            case self::CLOSING_TAG:
                ?>

                <?php
        }
    }

    protected function echoBriefDescription() {
        echo "Bad practice detected";
    }
}
