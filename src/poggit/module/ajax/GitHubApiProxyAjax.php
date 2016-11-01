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

namespace poggit\module\ajax;

use poggit\exception\GitHubAPIException;
use poggit\Poggit;
use poggit\session\SessionUtils;

class GitHubApiProxyAjax extends AjaxModule {
    protected function impl() {
        if(!isset($_REQUEST["url"])) {
            $this->errorBadRequest("Missing parameter 'url'");
        }
        $url = $_REQUEST["url"];
        $post = json_decode($_REQUEST["input"] ?? "{}");
        $method = strtoupper($_REQUEST["method"] ?? "GET");
        header("Content-Type: application/json");
        $tk = SessionUtils::getInstance()->getAccessToken();
        try {
            echo json_encode(Poggit::ghApiCustom($url, $method, $post, $tk),
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | (($_REQUEST["beautify"] ?? false) ? JSON_PRETTY_PRINT : 0));
        } catch(GitHubAPIException $e) {
            echo json_encode($e, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | (($_REQUEST["beautify"] ?? false) ? JSON_PRETTY_PRINT : 0));
        }
    }

    public function getName() : string {
        return "proxy.api.gh";
    }
}
