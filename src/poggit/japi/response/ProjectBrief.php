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

namespace poggit\japi\response;

class ProjectBrief {
    /** @var RepoBrief|null */
    public $repo;
    /** @var int */
    public $projectId;
    /** @var string */
    public $name;
    /** @var string */
    public $path;
    /** @var int */
    public $type;
    /** @var string */
    public $framework;
    /** @var bool */
    public $langEnabled;
    /** @var int[] map from BuildClass to int */
    public $buildsCount;
    /** @var BuildBrief[]|int[]|null */
    public $latestBuild;
}
