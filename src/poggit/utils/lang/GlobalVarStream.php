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

namespace poggit\utils\lang;

class GlobalVarStream {
    const SCHEME_NAME = "global";

    private $var;
    private $pointer = 0;

    public function stream_open(string $path): bool {
        $url = parse_url($path);
        if($url === false) return false;
        if(!isset($GLOBALS[$url["path"]])) $GLOBALS[$url["path"]] = "";
        $this->var =& $GLOBALS[$url["path"]];
        return true;
    }

    public function stream_write(string $data): int {
        $this->var .= $data;
        return strlen($data);
    }

    public function stream_read(int $count): string {
        $start = $this->pointer;
        $end = ($this->pointer += $count);
        if($start >= strlen($this->var)) return "";
        if($end > strlen($this->var)) $end = strlen($this->var);
        return substr($this->var, $start, $end);
    }

    public function stream_tell(): int {
        return $this->pointer;
    }

    public function stream_eof(): bool {
        return $this->pointer < strlen($this->var);
    }

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
