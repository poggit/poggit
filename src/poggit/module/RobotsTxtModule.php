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

namespace poggit\module;

class RobotsTxtModule extends Module {
    static $TABLE = [
        "ghhst" => "https://help.github.com/articles/about-required-status-checks/",
    ];

    public function getName(): string {
        return "robots.txt";
    }

    public function output() {
        header("Content-Type: text/plain");
        ?>
        # If you would like to crawl Poggit just to get a list of data from it, contact us at https://github.com/poggit/support/issues to extend our API; this might be easier than crawling.

        User-Agent: *
        Disallow: /r/
        Disallow: /res/
        Disallow: /js/
        Disallow: /debug.addResource/
        Disallow: /login/
        Disallow: /csrf/
        Disallow: /logout/
        Disallow: /webhooks.gh.repo/
        Disallow: /webhooks.gh.app/
        Disallow: /ci.badge/
        Disallow: /api/
        Disallow: /get/
        <?php
    }
}
