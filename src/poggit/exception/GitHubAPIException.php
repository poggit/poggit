<?php

/*
 * pogit
 *
 * Copyright (C) 2016
 */

namespace poggit\exception;

class GitHubAPIException extends \RuntimeException {
    private $errorMessage;

    public function __construct(\stdClass $error) {
        $message = $error->message;
        $clone = clone $error;
        unset($clone->message, $clone->documentation_url);
        if(count(get_object_vars($clone)) > 0) {
            $message .= json_encode($clone);
        }
        parent::__construct("GitHub API error: " . $message);
        $this->errorMessage = $error->message;
    }

    public function getErrorMessage() {
        return $this->errorMessage;
    }
}
