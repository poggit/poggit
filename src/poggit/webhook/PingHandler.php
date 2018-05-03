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
use function count;
use function json_encode;
use const JSON_UNESCAPED_SLASHES;

class PingHandler extends WebhookHandler {
    public function handle(string &$repoFullName, string &$sha) {
        Meta::getLog()->i("Handling ping event from GitHub API for repo {$this->data->repository->full_name}");
        echo "Pong!\n";
        $rows = Mysql::query("SELECT repoId FROM repos WHERE webhookId = ?", "i", $this->data->hook_id);
        if(count($rows) === 0) {
            throw new WebhookException("No repo found with hook ID {$this->data->hook_id}\n" .
                json_encode($this->data, JSON_UNESCAPED_SLASHES), WebhookException::LOG_INTERNAL);
        }
        $expectedRepoId = (int) $rows[0]["repoId"];
        $gotRepoId = $this->data->repository->id;
        if($expectedRepoId !== $gotRepoId) {
            throw new WebhookException("Webhook ID {$this->data->hook_id} is associated to wrong repo $gotRepoId!\n" .
                "Should be associated to repo of ID $expectedRepoId", WebhookException::LOG_INTERNAL);
        }
        if($expectedRepoId !== $this->assertRepoId) {
            throw new WebhookException("webhookKey doesn't match webhook ID", WebhookException::LOG_INTERNAL);
        }
    }
}
