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

namespace poggit\module\api\rel;

use poggit\module\api\ApiHandler;
use poggit\module\api\response\ReleaseBrief;

class GetReleaseApi extends ApiHandler {
    public function process(\stdClass $request) {
        $name = $_POST["name"];
        $version = $_POST["version"];
        // TODO
        $result = null;
        $result = new ReleaseBrief();
        return [
            "result" => $result
        ];
    }
}
