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

namespace poggit\builder;

use poggit\Poggit;

class RepoZipball {
    private $file;
    private $zip;
    private $prefix;
    private $prefixLength;

    public function __construct(string $url, string $token) {
        $this->file = Poggit::getTmpFile(".zip");
        Poggit::curlToFile("https://api.github.com/" . $url, $this->file, Poggit::MAX_ZIPBALL_SIZE, "Authorization: bearer $token");
        $this->zip = new \ZipArchive();
        $status = $this->zip->open($this->file);
        if($status !== true) throw new \UnexpectedValueException("Failed opening zip $this->file: $status", $status);
        $this->prefix = $this->zip->getNameIndex(0);
        $this->prefixLength = strlen($this->prefix);
    }

    public function isFile(string $name): bool {
        return $this->zip->locateName($this->prefix . $name) !== false;
    }

    public function toName(int $index): string {
        return substr($this->zip->getNameIndex($index), $this->prefixLength);
    }

    public function getContentsByIndex(int $index): string {
        return $this->zip->getFromIndex($index);
    }

    public function getContents(string $name): string {
        return $this->zip->getFromName($this->prefix . $name);
    }

    public function countFiles(): int {
        return $this->zip->numFiles;
    }

    public function iterator(): \Iterator {
        return new class() implements \Iterator {
            /** @var RepoZipball */
            private $zipball;
            private $current;

            public function __construct(RepoZipball $zipball) {
                $this->zipball = $zipball;
            }

            public function current() {
                return $this->zipball->getContentsByIndex($this->current);
            }

            public function next() {
                $this->current++;
            }

            public function key() {
                return $this->zipball->toName($this->current);
            }

            public function valid() {
                return $this->current < $this->zipball->countFiles();
            }

            public function rewind() {
                $this->current = 0;
            }
        };
    }

    /**
     * @return \Iterator<string, \Closure>
     */
    public function callbackIterator(): \Iterator {
        return new class($this) implements \Iterator {
            /** @var RepoZipball */
            private $zipball;
            private $current;

            public function __construct(RepoZipball $zipball) {
                $this->zipball = $zipball;
            }

            public function current() {
                return function () {
                    return $this->zipball->getContentsByIndex($this->current);
                };
            }

            public function next() {
                $this->current++;
            }

            public function key() {
                return $this->zipball->toName($this->current);
            }

            public function valid() {
                return $this->current < $this->zipball->countFiles();
            }

            public function rewind() {
                $this->current = 0;
            }
        };
    }

    public function __destruct() {
        $this->zip->close();
        unlink($this->file);
    }
}
