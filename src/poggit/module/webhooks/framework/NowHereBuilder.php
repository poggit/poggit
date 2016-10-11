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

namespace poggit\module\webhooks\framework;

use poggit\model\ProjectThumbnail;
use poggit\module\webhooks\PushWebhookHandler;

class NowHereBuilder extends FrameworkBuilder {
    public function getName() : string {
        return "nowhere";
    }

    public function getVersion() : string {
        return "3.0";
    }

    public function build(PushWebhookHandler $handler, ProjectThumbnail $project, \Phar $phar) : array {
        // TODO: Implement build() method.
    }
}
