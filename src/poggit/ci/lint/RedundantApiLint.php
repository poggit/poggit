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

class RedundantApiLint extends BadPracticeLint {

    public $line = "x";
    public $file = "plugin.yml";

    /** @var string */
    public $api;

    public function problemAsNounPhrase(): string {
        return "Use of redundant API";
    }

    public function moreElaboration() {
        ?>
      <p>PocketMine APIs only need to be listed once for each major version in <code class="code">plugin.yml</code>,
        only the lowest supported major.minor.patch should be listed for each major version.</p>
      <p>For example:</p>
      <pre class="code">api: [<?= $this->api ?>.0.0, <?= $this->api ?>.1.2, <?= $this->api ?>.2.3]</pre>
      <p>The api <code class="code"><?= $this->api ?>.0.0</code> is the only useful api, the other two are redundant and useless as you are saying you support <code class="code"><?= $this->api ?>.0.0+</code> which includes <code class="code"><?= $this->api ?>.1.2</code> and <code class="code"><?= $this->api ?>.2.3</code></p>
        <?php
    }
}
