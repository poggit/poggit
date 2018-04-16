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

use function parse_url;
use function stream_wrapper_register;
use function strlen;
use function substr;
use const SEEK_CUR;
use const SEEK_END;
use const SEEK_SET;

/**
 * @deprecated
 */
class GlobalVarStream {
    const SCHEME_NAME = "global";

    private $var;
    private $pointer = 0;

    /** @noinspection PhpMethodNamingConventionInspection
     * @param string $path
     * @return bool
     */
    public function stream_open(string $path): bool {
        $url = parse_url($path);
        if($url === false) return false;
        if(!isset($GLOBALS[$url["path"]])) $GLOBALS[$url["path"]] = "";
        $this->var =& $GLOBALS[$url["path"]];
        return true;
    }

    /** @noinspection PhpMethodNamingConventionInspection
     * @param string $data
     * @return int
     */
    public function stream_write(string $data): int {
        $this->var .= $data;
        return strlen($data);
    }

    /** @noinspection PhpMethodNamingConventionInspection
     * @param int $count
     * @return string
     */
    public function stream_read(int $count): string {
        $start = $this->pointer;
        $end = ($this->pointer += $count);
        if($start >= strlen($this->var)) return "";
        if($end > strlen($this->var)) $end = strlen($this->var);
        return substr($this->var, $start, $end);
    }

    /** @noinspection PhpMethodNamingConventionInspection */
    public function stream_tell(): int {
        return $this->pointer;
    }

    /** @noinspection PhpMethodNamingConventionInspection */
    public function stream_eof(): bool {
        return $this->pointer < strlen($this->var);
    }

    /** @noinspection PhpMethodNamingConventionInspection
     * @param int $offset
     * @param int $whence
     * @return bool
     */
    public function stream_seek(int $offset, int $whence = SEEK_SET): bool {
        if($whence === SEEK_SET) {
            $this->pointer = $offset;
        } elseif($whence === SEEK_CUR) {
            $this->pointer += $offset;
        } elseif($whence === SEEK_END) {
            $this->pointer = strlen($this->var) + $offset;
        } else {
            return false;
        }
        return true;
    }

    public static function register() {
        stream_wrapper_register(self::SCHEME_NAME, self::class);
    }
}
