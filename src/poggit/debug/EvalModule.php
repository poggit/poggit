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

declare(strict_types=1);

namespace poggit\debug;

use poggit\Meta;
use poggit\utils\OutputManager;
use Throwable;
use function get_class;
use function htmlspecialchars;

class EvalModule extends DebugModule {
    public function output() {
        parent::output();

        if(isset($_POST["eval"])) {
            $ob = OutputManager::$tail->startChild();
            try {
                eval($_POST["eval"]);
            } catch(Throwable $e) {
                echo "Exception!\n";
                echo get_class($e) . ": " . $e->getMessage() . "\non " . $e->getFile() . ":" . $e->getLine();
                echo $e->getTraceAsString();
            }
            $data = $ob->terminateGet();
        }
        ?>
      <form action="<?= Meta::root() . Meta::getModuleName() ?>" method="post">
        <textarea name="eval" cols="160" rows="30"><?= $_POST["eval"] ?? "" ?></textarea>
        <input type="submit"/>
          <?php if(isset($data)) { ?>
            <textarea cols="160" rows="30" disabled><?= htmlspecialchars($data) ?></textarea>
          <?php } ?>
      </form>
        <?php
    }
}
