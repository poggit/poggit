<?php

/*
 * Poggit
 *
 * Copyright (C) 2016-2020 Poggit
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

namespace poggit\ci\lint;

use function htmlspecialchars;

class PhpstanLint extends BuildLint {
    public $level = BuildResult::LEVEL_LINT;

    /** @var string|null */
    public $file = null;
    /** @var int */
    public $line;
    /** @var string */
    public $message;
    /** @var string */
    public $api = "4"; //Default 4 for previous lints that don't have this property.

    public function echoHtml() {
        ?>
      <h5>PHPStan - API <?= htmlspecialchars($this->api) ?></h5>
      <p><?php if($this->file !== null){ ?>Problem found in <?= htmlspecialchars($this->file) ?> at line <?= $this->line ?>. :</p><?php } ?>
      <pre class="code"><?= htmlspecialchars($this->message) ?></pre>
        <?php
    }
}
