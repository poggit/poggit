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

namespace poggit\module\releases\index;

use poggit\module\Module;

class ReleaseListModule extends Module {
    /** @var ReleaseListPage */
    private $variant;

    public function getName() : string {
        return "plugins";
    }

    public function getAllNames() : array {
        return ["plugins", "pi", "index"];
    }

    public function output() {
        $query = array_filter(explode("/", $this->getQuery(), 2));
        try {
            if(count($query) === 0) {
                $this->variant = new SearchReleaseListPage($_REQUEST);
            } elseif(count($query) === 1) {
                switch($query[0]) {
                    case "cat":
                    case "category":
                    case "tag":
                    case "tags":
                        $this->variant = new ListTagsReleaseListPage($_REQUEST);
                        break;
                    default:
                        $this->variant = new SearchReleaseListPage($_REQUEST, <<<EOM
<p>Cannot understand your query</p> <!-- TODO implement more logic here -->
EOM
                        );
                        break;
                }
            } else {
                assert(count($query) === 2);
                list($c, $v) = $query;
                switch($c) {
                    case "by":
                    case "author":
                    case "authors":
                    case "in":
                    case "repo":
                        $this->variant = new PluginsByRepoReleaseListPage($v, $_REQUEST);
                        break;
                    case "called":
                    case "name":
                        $this->variant = new PluginsByNameReleaseListPage($v);
                    default:
                        $this->variant = new SearchReleaseListPage($_REQUEST, <<<EOM
<p>Cannot understand your query</p> <!-- TODO implement more logic here -->
EOM
                        );
                        break;
                }
            }
        } catch(AltReleaseListPageException $ex) {
            $this->variant = $ex->getAlt();
        }
        ?>
        <html>
        <head>
            <title><?= htmlspecialchars($this->variant->getTitle()) ?> | Plugins | Poggit</title>
            <?php $this->headIncludes("Poggit Plugins - " . $this->variant->getTitle(), "Search plugins") ?>
        </head>
        <body>
        <?php $this->bodyHeader() ?>
        <!-- Page variant: <?= (new \ReflectionClass($this->variant))->getShortName() ?> -->
        <div id="body">
            <?php $this->variant->output() ?>
        </div>
        </body>
        </html>
        <?php
    }
}
