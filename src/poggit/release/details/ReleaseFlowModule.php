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

namespace poggit\release\details;

use poggit\Meta;
use poggit\module\HtmlModule;
use function readfile;
use const poggit\RES_DIR;

class ReleaseFlowModule extends HtmlModule {
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
      <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
      <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-alpha.6/js/bootstrap.min.js"></script>
      <script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js"></script>
      <script src="<?= Meta::root() ?>js/jquery-ui.min.js"></script>
      <script src="<?= Meta::root() ?>js/releaseFlow.min.js"></script>
      </body>
      </html>
        <?php
    }
}
