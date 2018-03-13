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

namespace poggit\timeline;

use JsonSerializable;
use poggit\utils\internet\Mysql;
use poggit\utils\lang\Lang;
use stdClass;
use function json_encode;

abstract class TimeLineEvent implements JsonSerializable {
    const EVENT_WELCOME = 1;
    const EVENT_BUILD_COMPLETE = 2;
    const EVENT_BUILD_LINT = 3;
    const EVENT_NEW_CATEGORY_RELEASE = 4;
    const EVENT_NEW_PLUGIN_UPDATE = 5;

    public static $TYPES = [
        self::EVENT_WELCOME => WelcomeTimeLineEvent::class,
        self::EVENT_BUILD_COMPLETE => BuildCompleteTimeLineEvent::class,
        self::EVENT_BUILD_LINT => BuildLintTimeLineEvent::class,
        self::EVENT_NEW_CATEGORY_RELEASE => NewCategoryReleaseTimeLineEvent::class,
        self::EVENT_NEW_PLUGIN_UPDATE => NewPluginUpdateTimeLineEvent::class,
    ];

    /** @var int */
    public $eventId;
    /** @var int */
    public $created;
    /** @var int */
    public $type;

    public static function fromJson(int $eventId, int $created, int $type, stdClass $data): TimeLineEvent {
        $class = self::$TYPES[$type];
        /** @var TimeLineEvent $event */
        $event = new $class;
        $event->eventId = $eventId;
        $event->created = $created;
        $event->type = $type;
        Lang::copyToObject($data, $event);
        return $event;
    }

    public abstract function getType(): int;

    public abstract function output();

    public function jsonSerialize(): array {
        $ret = (array) $this;
        unset($ret["eventId"], $ret["created"]);
        return $ret;
    }

    public function dispatchFor(int $uid) {
        $eventId = $this->dispatch();
        Mysql::query("INSERT INTO user_timeline (eventId, userId) VALUES (?, ?)", "ii", $eventId, $uid);
    }

    public function dispatch(): int {
        return Mysql::query("INSERT INTO event_timeline (type, details) VALUES (?, ?)",
            "is", $this->getType(), json_encode($this))->insert_id;
    }
}
