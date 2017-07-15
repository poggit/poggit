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

namespace poggit\release\submit;

use poggit\Meta;
use poggit\module\AjaxModule;
use poggit\utils\lang\Lang;

class NewSubmitAjax extends AjaxModule {
    public function getName(): string {
        return "submit.new.ajax";
    }

    protected function impl() {
        $json = Meta::getInput();
        $data = json_decode($json);
        $form = $data->form;
        $action = $data->action;
        $token = $data->submitFormToken;
        if(!isset($_SESSION["poggit"]["submitFormToken"][$token])) $this->errorAccessDenied("Wrong SFT! Did you click the submit button twice?");
        $args = $_SESSION["poggit"]["submitFormToken"][$token];
        unset($_SESSION["poggit"]["submitFormToken"][$token]); // TODO: if submission error, do not unset

        $submission = new PluginSubmission;
        $submission->args = $args["args"];
        $submission->mode = $args["mode"];
        $submission->icon = $args["icon"];
        $submission->action = $action;
        Lang::copyToObject($args["fillDefaults"], $submission);
        Lang::copyToObject($form, $submission);
        try {
            Lang::nonNullFields($submission);
        } catch(\InvalidArgumentException $e) {
            $this->errorBadRequest($e->getMessage());
        }


        echo json_encode([
            "status" => false,
            "error" => "",
            "input" => $data
        ]);
    }
}
