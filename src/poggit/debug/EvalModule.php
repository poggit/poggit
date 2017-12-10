<?php

/*
 *
 * poggit
 *
 * Copyright (C) 2017 SOFe
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 */

declare(strict_types=1);

namespace poggit\debug;

use poggit\Meta;
use poggit\utils\OutputManager;

class EvalModule extends DebugModule {
    public function getName(): string {
        return "eval";
    }

    public function output() {
        parent::output();

        if(isset($_POST["eval"])) {
            $ob = OutputManager::$tail->startChild();
            eval($_POST["eval"]);
            $data = $ob->terminateGet();
        }
        ?>
      <form action="<?= Meta::root() ?>eval" method="post">
        <textarea name="eval" cols="160" rows="30"><?= $_POST["eval"] ?? "" ?></textarea>
        <input type="submit"/>
          <?php if(isset($data)) { ?>
            <textarea cols="160" rows="30" disabled><?= htmlspecialchars($data) ?></textarea>
          <?php } ?>
      </form>
        <?php
    }
}
