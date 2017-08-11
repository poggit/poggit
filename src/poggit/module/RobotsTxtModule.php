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
    public function getName(): string {
        return "robots.txt";
    }

    public function output(): void {
        header("Content-Type: text/plain");
        echo "# If you would like to crawl Poggit just to get a list of data from it, contact us at " .
            "https://github.com/poggit/support/issues to extend our API; this might be easier than crawling.\r\n" .
            "User-Agent: *\r\n";
        foreach(ProxyLinkModule::TABLE as $name => $v) {
            echo "Disallow: /$name\r\n";
        }
        foreach([
                    "r",
                    "login",
                    "csrf",
                    "logout",
                    "webhooks.gh.repo",
                    "webhooks.gh.app",
                    "ci.badge",
                    "ci.shield",
                    "api",
                    "get"
                ] as $name) {
            echo "Disallow: /$name\r\n";
        }
    }
}
