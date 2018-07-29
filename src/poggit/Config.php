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

namespace poggit;

use poggit\release\Release;

class Config {
    const MAX_PHAR_SIZE = 2 << 20;
    const MAX_ZIPBALL_SIZE = 10 << 20;
    const MAX_RAW_VIRION_SIZE = 5 << 20;

    const MAX_REVIEW_LENGTH = 512;
    const MAX_VERSION_LENGTH = 20;
    const MAX_KEYWORD_COUNT = 100;
    const MAX_KEYWORD_LENGTH = 20;
    const MIN_SHORT_DESC_LENGTH = 10;
    const MAX_SHORT_DESC_LENGTH = 128;
    const MIN_DESCRIPTION_LENGTH = 100;
    const MAX_LICENSE_LENGTH = 51200;
    const MIN_CHANGELOG_LENGTH = 10;

    const MAX_WEEKLY_BUILDS = 50;
    const MAX_WEEKLY_PROJECTS = 6;
    const RECENT_BUILDS_RANGE = 86400;
    const MIN_PUBLIC_RELEASE_STATE = Release::STATE_VOTED;
    const MIN_DEV_STATE = Release::STATE_VOTED; // minimum state required to get development builds shipping
    const VOTED_THRESHOLD = 5;
}
