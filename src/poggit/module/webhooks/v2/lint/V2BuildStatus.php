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

namespace poggit\module\webhooks\v2\lint;

use poggit\Poggit;

abstract class V2BuildStatus {
    /** @var string|null */
    public $name;

    public abstract function echoHtml();

    public function jsonSerialize() {
        $this->name = (new \ReflectionClass($this))->getShortName();
        return $this;
    }

    public static function unserialize($data) : V2BuildStatus {
        $class = __NAMESPACE__ . "\\" . $data->name;
        $object = new $class;
        Poggit::copyToObject($data, $object);
        return $object;
    }
}
