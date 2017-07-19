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
use poggit\release\SubmitException;
use poggit\resource\ResourceManager;
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
        Lang::copyToObject($form, $submission); // do this before other assignments to prevent overriding
        $submission->action = $action;
        Lang::copyToObject($args, $submission);
        if($submission->mode !== "submit") {
            $submission->name = $submission->refRelease->name;
        }

        try {
            $submission->validate();
            $submission->resourcify();
            $submission->processArtifact();
            $submission->save();
        } catch(SubmitException $e) {
            $this->errorBadRequest($e->getMessage());
        }

        $artifactPath = ResourceManager::getInstance()->createResource("phar", "application/octet-stream", [], $artifactId);


        $this->errorBadRequest("Not implemented yet");
    }

    public function errorBadRequest(string $message, bool $escape = true) {
        echo json_encode([
            "status" => false,
            "error" => $message,
            "input" => json_decode(Meta::getInput()),
        ]);
    }
}
