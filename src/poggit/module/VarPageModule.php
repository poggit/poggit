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

use poggit\output\OutputManager;

abstract class VarPageModule extends Module {
    /** @var VarPage */
    public $varPage;

    public function output() {
        try {
            $this->selectPage();
            throw new \TypeError("No page returned");
        } catch(VarPage $page) {
            $this->varPage = $page;
        }
        $minifier = OutputManager::startMinifyHtml();
        ?>
        <html>
        <head
            prefix="og: http://ogp.me/ns# fb: http://ogp.me/ns/fb# object: http://ogp.me/ns/object# article: http://ogp.me/ns/article# profile: http://ogp.me/ns/profile#">
            <?php
            $ogResult = $this->varPage->og();
            if(is_array($ogResult)) {
                list($type, $link) = $ogResult;
            } else {
                $type = $ogResult;
                $link = "";
            }
            $title = htmlspecialchars($this->varPage->getTitle() . $this->titleSuffix());
            $this->headIncludes($title, $this->varPage->getMetaDescription(), $type, $link);
            $this->includeJs("build");
            echo '<title>';
            echo $title;
            echo '</title>';
            ?>
        </head>
        <body>
        <?php $this->bodyHeader() ?>
        <!-- VarPage: <?= get_class($this->varPage) ?> -->
        <div id="body">
            <?php
            $this->moduleHeader();
            echo "<div class='";
            echo implode(" ", $this->varPage->bodyClasses());
            echo "'>";
            $this->varPage->output();
            echo "</div>";
            $this->moduleFooter();
            ?>
        </div>
        </body>
        </html>
        <?php
        OutputManager::endMinifyHtml($minifier);
    }

    public function moduleHeader() {
    }

    public function moduleFooter() {
    }

    protected abstract function selectPage();

    protected function titleSuffix() : string {
        return " | Poggit";
    }
}
