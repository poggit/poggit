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

namespace poggit\module\releases\submit;

use poggit\module\ajax\AjaxModule;
use poggit\Poggit;
use poggit\release\PluginRelease;
use poggit\release\SubmitException;

class PluginSubmitAjax extends AjaxModule {
    protected function impl() {
        $data = json_decode(Poggit::getInput());
        if(!($data instanceof \stdClass)) $this->errorBadRequest("Invalid JSON: " . $data === null ? json_last_error_msg() : "Not an object");
        try {
            $release = PluginRelease::fromSubmitJson($data);
            $version = $release->submit();
        } catch(SubmitException $e) {
            $this->errorBadRequest($e->getMessage());
            die;
        }

        echo json_encode(["release" => $release, "version" => $version]);
    }

    public function getName(): string {
        return "release.submit.ajax";
    }
}
