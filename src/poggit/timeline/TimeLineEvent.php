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

namespace poggit\timeline;

use poggit\Poggit;

abstract class TimeLineEvent implements \JsonSerializable {
    const EVENT_WELCOME = 1;
    const EVENT_BUILD_COMPLETE = 2;
    const EVENT_BUILD_LINT = 3;
    const EVENT_NEW_CATEGORY_RELEASE = 4;
    const EVENT_NEW_PLUGIN_UPDATE = 5;

    static $TYPES = [
        self::EVENT_WELCOME => WelcomeTimeLineEvent::class,
        self::EVENT_BUILD_COMPLETE => BuildCompleteTimeLineEvent::class,
        self::EVENT_BUILD_LINT => BuildLintTimeLineEvent::class,
        self::EVENT_NEW_CATEGORY_RELEASE => NewCategoryReleaseTimeLineEvent::class,
        self::EVENT_NEW_PLUGIN_UPDATE => NewPluginUpdateTimeLineEvent::class,
    ];

//    public $_class;

    public static function fromJson(int $type, \stdClass $data) : TimeLineEvent {
        $class = self::$TYPES[$type];
        /** @var TimeLineEvent $event */
        $event = new $class;
        Poggit::copyToObject($data, $event);
        return $event;
    }

    public abstract function getType() : int;

    public abstract function output();

    public function jsonSerialize() {
//        $this->_class = get_class($this);
        return $this;
    }

    public function dispatchFor(int $uid) {
        Poggit::queryAndFetch("INSERT INTO user_timeline (uid, type, details) VALUES (?, ?, ?)",
            "iis", $uid, $this->getType(), json_encode($this));
    }
}
