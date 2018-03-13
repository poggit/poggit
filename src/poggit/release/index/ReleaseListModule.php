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

namespace poggit\release\index;

use poggit\module\VarPageModule;
use poggit\utils\lang\Lang;
use function assert;
use function count;

class ReleaseListModule extends VarPageModule {
    const DISPLAY_NAME = "Releases";

    protected function selectPage() {
        $query = Lang::explodeNoEmpty("/", $this->getQuery(), 2);
        if(count($query) === 0) {
            throw new MainReleaseListPage($_REQUEST);
        } elseif(count($query) === 1) {
            switch($query[0]) {
                case "cat":
                case "category":
                case "categories":
                case "tag":
                case "tags":
                    throw new ListCategoriesPage;
                case "authors":
                    throw new ListAuthorsPage($_REQUEST);
                default:
                    throw new MainReleaseListPage($_REQUEST, <<<EOM
<p>Cannot understand your query</p> <!-- TODO implement more logic here -->
EOM
                    );
            }
        } else {
            assert(count($query) === 2);
            list($c, $v) = $query;
            switch($c) {
                case "by":
                case "author":
                case "authors":
                    throw new SearchPluginsByAuthorPage($v, $_REQUEST);
                case "called":
                case "name":
                    throw new SearchPluginsByNamePage($v);
                default:
                    throw new MainReleaseListPage($_REQUEST, <<<EOM
<p>Cannot understand your query</p> <!-- TODO implement more logic here -->
EOM
                    );
            }
        }
    }

    protected function titleSuffix(): string {
        return " | Poggit Release";
    }
}
