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

namespace poggit\builder\lint;

class DeadCodeLint extends BadPracticeLint {
    /** @var string */
    public $ctrl;

    public function problemAsNounPhrase(): string {
        return "Dead code";
    }

    public function moreElaboration() {
        ?>
        <p>The code above is placed behind a <code class="code"><?= $this->ctrl ?></code> statement, which terminates code flow in the block. Without appropriate entry points (e.g. <code>goto</code> labels, switch block <code>case</code>s, it is impossible that this line of code is run. While keeping this line does not affect performance or functionality, it is inappropriate to keep redundant code that would only decrease code readability. You are hence strongly recommended to delete this line, had it not been caused by misplacement of code.</p>
        <?php
    }
}
