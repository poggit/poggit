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

namespace poggit\builder\lint;

abstract class BadPracticeLint extends BuildLint {
    public $level = BuildResult::LEVEL_LINT;

    /** @var string */
    public $file;
    /** @var int */
    public $line;
    /** @var string */
    public $code;
    /** @var int[][] */
    public $hlSects = [];

    public function jsonSerialize() {
        $sects = $this->hlSects;
        usort($sects, function ($a, $b) {
            return $a[0] <=> $b[0];
        });
        $final = [];
        foreach($sects as list($start, $end)) {
            foreach($final as &$prev) {
                if($prev[1] >= $start) {
                    if($prev[1] < $end) $prev[1] = $end;
                    continue 2;
                }
            }
            $final[] = [$start, $end];
        }
        $this->hlSects = $final;
        return parent::jsonSerialize();
    }

    public function echoHtml() {
        ?>
        <h5><?= $this->problemAsNounPhrase() ?></h5>
        <p>On line <?= $this->line ?> at <?= htmlspecialchars($this->file) ?>:</p>
        <pre class="code"><?php
            $offset = 0;
            foreach($this->hlSects as list($start, $end)) {
                echo htmlspecialchars(substr($this->code, $offset, $start));
                echo "<span class='highlighted'>";
                echo htmlspecialchars(substr($this->code, $start, $end));
                echo "</span>";
                $offset = $end;
            }
            echo htmlspecialchars(substr($this->code, $offset));
            ?></pre>
        <?php $this->moreElaboration() ?>
        <?php
    }

    public abstract function problemAsNounPhrase() : string;

    public abstract function moreElaboration();
}
