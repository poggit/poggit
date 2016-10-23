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

namespace poggit\model;

class ReleaseMeta {
    const TYPE_CATEGORY = 1;
    const TYPE_PERMISSION = 2;
    const TYPE_REQUIREMENT = 3;
    const TYPE_COMPATIBLE_SPOON = 4;
    const TYPE_OFFICIAL_REVIEW = 5;
    const TYPE_USER_REVIEW = 6;

    public static $CATEGORIES = [
        0 => "Admin Tools",
        1 => "Anti-Griefing Tools",
        2 => "Chat-Related",
        3 => "Developer Tools",
        4 => "Economy",
        5 => "Fun",
        6 => "General",
        7 => "Informational",
        8 => "Mechanics",
        9 => "Miscellaneous",
        10 => "Teleportation",
        11 => "World Editing and Management",
        12 => "World Generators"
    ];
}
