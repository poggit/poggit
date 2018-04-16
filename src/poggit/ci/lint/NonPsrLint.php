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
use function max;
use function min;
use function rand;
use function str_replace;
use function strlen;

class NonPsrLint extends BadPracticeLint {
    /** @var string */
    public $class;

    public function problemAsNounPhrase(): string {
        return "Violation of PSR-0";
    }

    public function moreElaboration() {
        ?>
      <p>PocketMine uses an autoloader that autoloads classes using the <a href="http://www.php-fig.org/psr/psr-0/">
          PSR-0</a> standard. In simple words, every class should be placed in a class file whose path depends on
        the class's namespace + class name in the <code class="code">src</code> directory.<br/>
        Moreover, the case should also be equal, because on case-sensitive systems, your class may not be loaded
        because the autoloader tries to load your class using the case in your namespace/class name, while the
        system does not think that your class file represents the path that the autoloader required.<br/>
        Classes should be placed in these paths:</p>
      <table class="info-table">
        <tr>
          <th>Fully-qualified class name</th>
          <th>Class location</th>
        </tr>
        <tr>
          <td>Foo\Bar</td>
          <td>src/Foo/Bar.php</td>
        </tr>
        <tr>
          <td><?= htmlspecialchars($this->class) ?></td>
          <td>src/<?= htmlspecialchars(str_replace("\\", "/", $this->class)) ?>.php</td>
        </tr>
        <tr>
            <?php $randomId = rand(); ?>
          <td><input id="<?= $randomId ?>-in" type="text" size="<?= min(max(strlen($this->class), 26), 50) ?>"
                     placeholder="Try with other class names"
                     onkeyup='document.getElementById("<?= $randomId ?>-out").innerText =
                         "src/"+document.getElementById("<?= $randomId ?>-in").value.replace(/\\/g,"/")
                         + ".php"'>
          </td>
          <td id="<?= $randomId ?>-out"></td>
        </tr>
      </table>
        <?php
    }
}
