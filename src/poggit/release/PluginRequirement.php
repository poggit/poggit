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

namespace poggit\release;

class PluginRequirement {
    const REQUIREMENT_MAIL = 1;
    const REQUIREMENT_MYSQL = 2;
    const REQUIREMENT_API_TOKEN = 3;
    const REQUIREMENT_PASSWORD = 4;
    const REQUIREMENT_OTHER = 5;
    public static $NAMES_TO_CONSTANTS = [
        "mail" => self::REQUIREMENT_MAIL,
        "mysql" => self::REQUIREMENT_MYSQL,
        "apiToken" => self::REQUIREMENT_API_TOKEN,
        "password" => self::REQUIREMENT_PASSWORD,
        "other" => self::REQUIREMENT_OTHER,
    ];
    public static $CONST_TO_DETAILS = [
        PluginRequirement::REQUIREMENT_MAIL => [
            "name" => "Mail server",
            "details" => "Which mail server software is recommended?",
        ],
        PluginRequirement::REQUIREMENT_MYSQL => [
            "name" => "MySQL server",
            "details" => "Does the plugin require any special MySQL permissions?",
        ],
        PluginRequirement::REQUIREMENT_API_TOKEN => [
            "name" => "API token",
            "details" => "From which website?",
        ],
        PluginRequirement::REQUIREMENT_PASSWORD => [
            "name" => "Password",
            "details" => "(should be empty)",
        ],
        PluginRequirement::REQUIREMENT_OTHER => [
            "name" => "Other",
            "details" => "Please specify...",
        ],
    ];

    /** @var int */
    public $type;
    /** @var string */
    public $details;
    /** @var bool */
    public $isRequire;

    public static function fromJson($reqr): PluginRequirement {
        if(!isset($reqr->type, $reqr->enhance)) throw new SubmitException("Param 'reqr' is incorrect");
        $type = $reqr->type;
        if(!isset(self::$NAMES_TO_CONSTANTS[$type])) throw new SubmitException("Unknown requirement type $type");
        $details = $reqr->details ?? "";
        $isRequired = $reqr->enhance === "requirement";
        return new PluginRequirement(self::$NAMES_TO_CONSTANTS[$type], $details, $isRequired);
    }

    public function __construct(int $type, string $details, bool $isRequire) {
        $this->type = $type;
        $this->details = $details;
        $this->isRequire = $isRequire;
    }
}
