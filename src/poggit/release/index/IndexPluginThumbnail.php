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

namespace poggit\release\index;

class IndexPluginThumbnail {
    /** @var int */
    public $id;
    /** @var int */
    public $projectId;
    public /** @noinspection PhpPropertyNamingConventionInspection
     * @var int
     */
        $parent_releaseId;
    /** @var string */
    public $name;
    /** @var string */
    public $version;
    /** @var string */
    public $author;
    /** @var string */
    public $iconUrl;
    /** @var string */
    public $shortDesc;
    /** @var int[] */
    public $categories;
    /** @var array */
    public $spoons;
    /** @var int */
    public $creation;
    /** @var int */
    public $updateTime;
    /** @var int */
    public $state;
    /** @var int */
    public $flags;
    /** @var int */
    public $isPrivate;
    /** @var string */
    public $framework;
    /** @var bool */
    public $isMine;
    /** @var int */
    public $dlCount;
    /** @var string */
    public $assignee;

    /** @var int|null */
    public $scoreCount;
    /** @var int|null */
    public $scoreTotal;
    /** @var int|null */
    public $totalDl;

    /** @var array|null */
    public $stats; // hack
}
