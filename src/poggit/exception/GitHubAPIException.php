<?php

/*
 * pogit
 *
 * Copyright (C) 2016
 */

namespace poggit\exception;

class GitHubAPIException extends \RuntimeException {
    private $errorMessage;

    public function __construct(string $message) {
        parent::__construct("GitHub API error: " . $message);
        $this->errorMessage = $message;
    }

    public function getErrorMessage() {
        return $this->errorMessage;
    }
}
