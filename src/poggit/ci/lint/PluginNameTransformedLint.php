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

namespace poggit\ci\lint;

use function htmlspecialchars;

class PluginNameTransformedLint extends BuildLint {
    public $level = BuildResult::LEVEL_LINT;

    /** @var string */
    public $oldName, $fixedName;

    public function echoHtml() {
        ?>
      <p>Plugin name will be <code class="code"><?= htmlspecialchars($this->fixedName) ?></code> instead of
        <code class="code"><?= htmlspecialchars($this->oldName) ?></code></p>
      <p>Only characters <code class="code">A-Za-z0-9 _.-</code> are allowed in plugin names.</p>
        <?php
    }
}
