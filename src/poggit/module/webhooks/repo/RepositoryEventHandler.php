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

namespace poggit\module\webhooks\repo;

use poggit\Poggit;

class RepositoryEventHandler extends RepoWebhookHandler {
    public function handle() {
        if($this->data->repository->id !== $this->assertRepoId) {
            throw new StopWebhookExecutionException("webhookKey does not match sent repo ID");
        }

        if($this->data->action === "deleted") {
            Poggit::getLog()->i("Repo #$this->assertRepoId ({$this->data->repository->full_name}) deleted");

            Poggit::queryAndFetch("DELETE builds.* FROM builds 
                INNER JOIN projects on builds.projectId = projects.projectId
                WHERE projects.repoId = ?", "i", $this->assertRepoId);
//            Poggit::queryAndFetch("DELETE releases.* FROM release_meta
//                INNER JOIN releases on releases.releaseId = release_meta.releaseId
//                INNER JOIN projects on releases.projectId = projects.projectId
//                WHERE projects.repoId = ?", "i", $this->assertRepoId);
            Poggit::queryAndFetch("DELETE releases.* FROM releases INNER JOIN projects on releases.projectId = projects.projectId
                WHERE projects.repoId = ?", "i", $this->assertRepoId);
            Poggit::queryAndFetch("DELETE projects.* FROM projects WHERE repoId = ?", "i", $this->assertRepoId);
            Poggit::queryAndFetch("DELETE repos.* FROM repos WHERE repoId = ?", "i", $this->assertRepoId);
        }
    }
}
