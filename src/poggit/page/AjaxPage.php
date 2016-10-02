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

namespace poggit\page;

use poggit\output\OutputManager;
use poggit\Poggit;
use poggit\session\SessionUtils;
use function poggit\getRequestPath;

abstract class AjaxPage extends Page {
    public final function output() {
        $session = SessionUtils::getInstance();
        if($this->needLogin() and !$session->isLoggedIn()) {
            \poggit\redirect(".");
        }
        if(!SessionUtils::getInstance()->validateCsrf($_REQUEST["csrf"] ?? "this will never match")) {
            if($this->fallback()) {
                http_response_code(403);
                Poggit::getLog()->w("CSRF failed");
                die;
            }
            return;
        }
        $this->impl();
    }

    protected function needLogin() : bool {
        return true;
    }

    /**
     * @return bool true if the request should end with a 403, false if the page should be displayed as a webpage
     */
    protected function fallback() : bool {
        return false;
    }

    protected function errorBadRequest(string $message) {
        OutputManager::terminateAll();
        http_response_code(400);
        echo json_encode([
            "message" => $message,
            "source_url" => "https://github.com/poggit/poggit"
        ]);
        die;
    }

    protected abstract function impl();
}
