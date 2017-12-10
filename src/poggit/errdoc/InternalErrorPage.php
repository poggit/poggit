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

namespace poggit\errdoc;

use poggit\Meta;
use poggit\module\Module;
use const poggit\RES_DIR;

class InternalErrorPage extends Module {
    public function output() {
        http_response_code(500);
        ?>
        <!-- Request ID: <?= $_REQUEST["id"] ?? Meta::getRequestId() ?> -->
        <html>
        <head prefix="og: http://ogp.me/ns# fb: http://ogp.me/ns/fb# object: http://ogp.me/ns/object# article: http://ogp.me/ns/article# profile: http://ogp.me/ns/profile#">
          <style type="text/css"><?php readfile(RES_DIR . "style.css") ?></style>
          <script>
            function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)})(window,document,'script','https://www.google-analytics.com/analytics.js','ga');
            ga('create','UA-93677016-1','auto');
            ga('send', 'event', 'Special', 'Error', window.location.pathname);
          </script>
          <title>500 Internal Server Error</title>
        </head>
        <body>
        <div id="body">
            <h1>500 Internal Server Error</h1>
            <p>A server internal error occurred. Please use this request ID for reference if you need support:
                <code class="code"><?= $_REQUEST["id"] ?? Meta::getRequestId() ?></code></p>
          <p>Logging out may solve the problem.
            <span class="action" onclick="location.assign('<?= Meta::root() ?>logout')">Have a try</span></p>
            <a class="twitter-timeline" data-width="350" data-height="600" data-theme="light" data-link-color="#E81C4F"
               href="https://twitter.com/poggitci?ref_src=twsrc%5Etfw">Tweets by @poggitci</a>
            <script async src="//platform.twitter.com/widgets.js" charset="utf-8"></script>
        </div>
        </body>
        </html>
        <?php
    }
}
