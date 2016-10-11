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

namespace poggit\module\webhooks\buildstatus;

abstract class BuildStatus implements \JsonSerializable {
    const STATUS_LINT = 2;
    const STATUS_WARN = 3;
    const STATUS_ERR = 4;

    public static $STATUS_HUMAN = [
        0 => "passed",
        self::STATUS_LINT => "lint",
        self::STATUS_WARN => "warn",
        self::STATUS_ERR => "error",
    ];
    public static $STATUS_COLOR_CLASS = [
        self::STATUS_LINT => "blue",
        self::STATUS_WARN => "yellow",
        self::STATUS_ERR => "red"
    ];

    private $name;
    public $status;

    public function jsonSerialize() {
        $this->name = (new \ReflectionClass($this))->getShortName();
        return $this;
    }

    public static function fromObject(\stdClass $object) : BuildStatus {
        $status = (new \ReflectionClass(__NAMESPACE__ . "\\" . $object->name))->newInstance();
        foreach($object as $key => $value) {
            $status->{$key} = $value;
        }
        return $status;
    }

    public function outputString() {
        ?>
        <h3>
            <span class="colored-bullet <?= self::$STATUS_COLOR_CLASS[$this->status] ?>"></span>
            <?php
            switch($this->status) {
                case self::STATUS_ERR:
                    echo "Error:";
                    break;
                case self::STATUS_WARN:
                    echo "Warning:";
                    break;
                case self::STATUS_LINT:
                    echo "Lint:";
                    break;
            }
            echo " ", $this->echoBriefDescription() ?? "";
            ?>
        </h3>
        <?php
        $this->echoString();
    }

    protected abstract function echoString();

    protected abstract function echoBriefDescription();
}
