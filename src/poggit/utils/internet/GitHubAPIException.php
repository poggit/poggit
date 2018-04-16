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

namespace poggit\utils\internet;

use InvalidArgumentException;
use RuntimeException;
use stdClass;
use function assert;
use function count;
use function get_object_vars;
use function json_encode;

class GitHubAPIException extends RuntimeException {
    private $url;
    private $errorMessage;

    public function __construct(string $url, stdClass $error) {
        if(!isset($error->message)) {
            throw new InvalidArgumentException("Not a real error ($url): " . json_encode($error));
        }
        assert(isset($error->message));
        $message = $error->message;
        $clone = clone $error;
        unset($clone->message, $clone->documentation_url);
        if(count(get_object_vars($clone)) > 0) {
            $message .= json_encode($clone);
        }
        parent::__construct("GitHub API error when accessing $url: " . $message);
        $this->url = $url;
        $this->errorMessage = $error->message;
    }

    public function getUrl(): string {
        return $this->url;
    }

    public function getErrorMessage(): string {
        return $this->errorMessage;
    }
}
