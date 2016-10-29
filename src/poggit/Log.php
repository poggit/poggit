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

namespace poggit;

class Log {
    const LEVEL_VERBOSE = "verbose";
    const LEVEL_DEBUG = "debug";
    const LEVEL_INFO = "info";
    const LEVEL_WARN = "warn";
    const LEVEL_ERROR = "error";
    const LEVEL_ASSERT = "assert";

    private $streams = [];

    public function __construct() {
        if(!is_dir(LOG_DIR)) {
            mkdir(LOG_DIR, 0777, true);
        }
    }

    public function v(string $message) {
        $this->log(self::LEVEL_VERBOSE, $message);
    }

    public function d(string $message) {
        $this->log(self::LEVEL_DEBUG, $message);
    }

    public function i(string $message) {
        $this->log(self::LEVEL_INFO, $message);
    }

    public function w(string $message) {
        $this->log(self::LEVEL_WARN, $message);
    }

    public function e(string $message) {
        $this->log(self::LEVEL_ERROR, $message);
    }

    public function wtf(string $message) {
        $this->log(self::LEVEL_ASSERT, $message);
    }

    private function log(string $level, string $message) {
        if(!isset($this->streams[$level])) {
            $this->createStream($level);
        }
//        fwrite($this->streams[$level], date('M j H:i:s ') . $message . "\n");
        file_put_contents(LOG_DIR . "$level.log",
            date('M j H:i:s') . strstr((string) round(microtime(true), 3), ".") . " " . $message . "\n", FILE_APPEND);
    }

    private function createStream(string $level) {
//        $this->streams[$level] = fopen(LOG_DIR . "$level.log", "ab");
    }

    public function __destruct() {
//        foreach($this->streams as $stream) {
//            fclose($stream);
//        }
    }
}
