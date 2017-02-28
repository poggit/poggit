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

namespace poggit\webhook;

use poggit\Poggit;
use poggit\utils\internet\MysqlUtils;

class RepositoryEventHandler extends RepoWebhookHandler {
    public function handle() {
        Poggit::getLog()->i("Handling repo event from GitHub API for repo {$this->data->repository->full_name}");
        if($this->data->repository->id !== $this->assertRepoId) {
            throw new StopWebhookExecutionException("webhookKey does not match sent repo ID");
        }

        if($this->data->action === "deleted") {
            Poggit::getLog()->w("Repo #$this->assertRepoId ({$this->data->repository->full_name}) deleted");
            MysqlUtils::query("DELETE repos.* FROM repos WHERE repoId = ?", "i", $this->assertRepoId);
        }
    }
}
