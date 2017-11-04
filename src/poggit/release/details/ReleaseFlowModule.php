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

namespace poggit\release\details;

use poggit\module\Module;
use const poggit\RES_DIR;

class ReleaseFlowModule extends Module {
    public function getName(): string {
        return "release.flow";
    }

    public function output() {
        ?>
      <html>
      <head
          prefix="og: http://ogp.me/ns# fb: http://ogp.me/ns/fb# object: http://ogp.me/ns/object# article: http://ogp.me/ns/article# profile: http://ogp.me/ns/profile#">
          <?php $this->headIncludes("Release Flowchart", "The lifecycle diagram of a Poggit plugin release") ?>
        <title>Release Flowchart</title>
        <style>
          svg [data-comment], svg [title] {
            cursor: hand;
          }
        </style>
      </head>
      <body>
      <div id="flow-svg-container">
          <?php readfile(RES_DIR . "release-flow.svg"); ?>
      </div>
      <?php
      Module::$jsList = ["jquery-ui", "releaseFlow"];
      $this->flushJsList();
      ?>
      </body>
      </html>
        <?php
    }
}
