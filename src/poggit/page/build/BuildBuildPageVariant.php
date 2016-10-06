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

namespace poggit\page\build;

class BuildBuildPageVariant extends BuildPageVariant {
    /** @var string */
    private $user;
    /** @var string */
    private $repo;
    /** @var string */
    private $project;
    /** @var string */
    private $build;

    public function __construct(string $user, string $repo, string $project, string $build) {
        $this->user = $user;
        $this->repo = $repo;
        $this->project = $project;
        $this->build = $build;
    }

    public function getTitle() : string {

    }
}
