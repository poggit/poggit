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

namespace poggit\webhook;

use poggit\Meta;
use poggit\utils\internet\Mysql;

class RepositoryEventHandler extends WebhookHandler {
    public function handle(string &$repoFullName, string &$sha) {
        Meta::getLog()->i("Handling repo event from GitHub API for repo {$this->data->repository->full_name}");
        if($this->data->repository->id !== $this->assertRepoId) {
            throw new WebhookException("webhookKey does not match sent repo ID", WebhookException::LOG_INTERNAL | WebhookException::OUTPUT_TO_RESPONSE);
        }

        if($this->data->action === "deleted") {
            Meta::getLog()->w("Repo #$this->assertRepoId ({$this->data->repository->full_name}) deleted");
            Mysql::query("DELETE repos.* FROM repos WHERE repoId = ?", "i", $this->assertRepoId);
        }
    }
}
