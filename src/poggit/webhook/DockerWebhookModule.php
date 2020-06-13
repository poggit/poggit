<?php

/*
 * Poggit
 *
 * Copyright (C) 2016-2020 Poggit
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

namespace poggit\webhook;

use poggit\Meta;
use poggit\module\Module;
use poggit\utils\lang\Lang;

class DockerWebhookModule extends Module {
    public function output() {
        if($_SERVER['REQUEST_METHOD'] !== 'POST' or !isset($_GET['key'])){
            $this->errorNotFound();
        }

        if($_GET['key'] !== Meta::getSecret("docker.webhookSecret")){
            $this->errorAccessDenied();
        }

        Meta::getLog()->v("Docker update data received.");

        $payload = json_decode(Meta::getInput(), true);

        if($payload === null){
            Meta::getLog()->w("Failed to decode data from docker, data: ".Meta::getInput());
            $this->errorBadRequest("Invalid data received.");
        }

        if($payload['repository']['repo_name'] !== "pmmp/poggit-phpstan"){
            Meta::getLog()->i("Ignoring docker update, repository is not used.");
            return;
        }

        if($payload['push_data']['tag'] !== 'latest'){
            Meta::getLog()->i("Ignoring docker update, tag not latest.");
            return;
        }

        Meta::getLog()->i("Updating docker image '{$payload['repository']['repo_name']}' locally, Image updated by '".
            $payload['push_data']['pusher']."' on '".date('D, d M Y H:i:s', $payload['push_data']['pushed_at'])."'");

        Lang::myShellExec("docker pull pmmp/poggit-phpstan:latest", $stdout, $stderr, $exitCode);

        if($exitCode !== 0){
            Meta::getLog()->e("Failed to update docker image.\nstderr: {$stderr}\nexitCode: {$exitCode}");
        } else {
            // Assuming the push to docker hub was not a 'fake'
            Meta::getLog()->i("Successfully updated image.");
        }
    }
}