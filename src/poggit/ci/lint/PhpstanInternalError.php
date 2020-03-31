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

class PhpstanInternalError extends V2BuildStatus {
    public $level = BuildResult::LEVEL_ERROR;

    /** @var string */
    public $exception;

    public function echoHtml() {
        ?>
      <h5>PHPStan Error</h5>
      <p>An error occurred while running PHPStan</p>
      <p><?= htmlspecialchars($this->exception) ?></p>
        <?php
    }
}
