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

namespace poggit\utils\lang;

use function fclose;
use function fopen;
use function fwrite;
use function implode;
use function rtrim;
use function strlen;

class TemporalHeaderlessWriter {
    private $stream;
    private $lastWrite = "";
    private $headers = [];

    public function __construct(string $file) {
        $this->stream = fopen($file, "wb");
    }

    public function write(/** @noinspection PhpUnusedParameterInspection */
        $ch, string $bytes): int {
        if($this->lastWrite !== "") {
            fwrite($this->stream, $this->lastWrite);
        }
        $this->lastWrite = $bytes;
        return strlen($bytes);
    }

    public function header(/** @noinspection PhpUnusedParameterInspection */
        $ch, string $bytes): int {
        $this->lastWrite = "";
        $this->headers[] = rtrim($bytes, "\r\n");
        return strlen($bytes);
    }


    public function close(): string {
        fwrite($this->stream, $this->lastWrite);
        fclose($this->stream);
        return implode("\r\n", $this->headers);
    }
}
