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

namespace poggit\module\ajax;

class SearchAjax extends AjaxModule {

    protected function impl() {
        // read post fields
        if(!isset($_POST["search"]) || !preg_match('%^[A-Za-z0-9_]{2,}$%', $_POST["search"])) $this->errorBadRequest("Invalid search field 'search'");
        Poggit::getLog()->v("Ajax.Search Complete for " . $_POST["search"]);
        $resultshtml = "<div class='searchresult'>" . $_POST["search"] . "</div>";
        echo json_encode([
            "html" => $resultshtml
        ]);
    }

    public function getName(): string {
        return "search.ajax";
    }
}
