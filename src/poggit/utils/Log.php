<?php

/*
 * Poggit
 *
 * Copyright (C) 2016-2017 Poggit
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

namespace poggit\utils;

use poggit\Meta;
use const poggit\LOG_DIR;

class Log {
    const LEVEL_VERBOSE = "verbose";
    const LEVEL_DEBUG = "debug";
    const LEVEL_INFO = "info";
    const LEVEL_WARN = "warn";
    const LEVEL_ERROR = "error";
    const LEVEL_ASSERT = "assert";

    public function __construct() {
    }

    public function jv($var) {
        $this->v(json_encode($var, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    public function jd($var) {
        /** @noinspection ForgottenDebugOutputInspection */
        $this->d(json_encode($var, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    public function ji($var) {
        $this->i(json_encode($var, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    public function jw($var) {
        $this->w(json_encode($var, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    public function je($var) {
        $this->e(json_encode($var, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    public function jwtf($var) {
        $this->wtf(json_encode($var, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
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
        $now = round(microtime(true), 3);
        $line = $month = date("M");
        $line .= date(" j H:i:s", $now) . str_pad(strstr((string) $now, "."), 4, "0", STR_PAD_RIGHT);
        $line .= " [" . Meta::getRequestId() . "] ";
        $line .= $message;
        $line .= "\n";
        file_put_contents(LOG_DIR . "$level.$month.log", $line, FILE_APPEND);
    }

    public function __destruct() {
//        foreach($this->streams as $stream) {
//            fclose($stream);
//        }
    }
}
