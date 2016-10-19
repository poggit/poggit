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

class PingHandler extends RepoWebhookHandler {
    public function handle() {
        echo "Pong!\n";
        $rows = Poggit::queryAndFetch("SELECT repoId FROM repos WHERE webhookId = ?", "i", $this->data->hook_id);
        if(count($rows) === 0) {
            throw new StopWebhookExecutionException("No repo found with hook ID {$this->data->hook_id}\n" .
                json_encode($this->data, JSON_UNESCAPED_SLASHES), 1);
        }
        if(((int) $rows[0]["repoId"]) === $this->data->repository->id) {
            throw new StopWebhookExecutionException("Webhook ID is associated to wrong repo!\nShould be associated to " .
                "repo of ID " . $rows[0]["repoId"], 1);
        }
    }
}
