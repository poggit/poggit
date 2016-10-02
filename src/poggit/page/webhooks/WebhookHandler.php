<?php

/*
 * Copyright 2016 poggit
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

namespace poggit\page\webhooks;

abstract class WebhookHandler {
    /** @var \stdClass */
    public $payload;

    /** @var bool */
    public $resultSuccess;
    /** @var string */
    public $resultMessage;

    public function __construct(\stdClass $payload) {
        $this->payload = $payload;
    }

    public abstract function handle();

    protected function refToBranch(string $ref) : string {
        assert(substr($ref, 0, 11) === "refs/heads/");
        return substr($ref, 11);
    }

    public function setResult(bool $success, string $message = "") : bool {
        $this->resultSuccess = $success;
        $this->resultMessage = $message;
        throw new \RuntimeException;
    }
}
