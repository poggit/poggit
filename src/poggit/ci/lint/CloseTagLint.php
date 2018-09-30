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

class CloseTagLint extends BadPracticeLint {
    public function problemAsNounPhrase(): string {
        return "Use of close tags";
    }

    public function moreElaboration() {
        ?>
      <p>PHP close tags <code class="code">?&gt;</code> should not be used in PocketMine plugins.</p>
      <p class="note">For PHP files of entirely code (in contrast to files for websites containing HTML fragments),
        the use of the PHP close tag is discouraged, because if you accidentally put spaces or newlines after the
        close tag, they may be echoed when the file was loaded, affecting the console output.</p>
        <?php
    }
}
