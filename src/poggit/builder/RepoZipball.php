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

namespace poggit\builder;

use poggit\Poggit;
use poggit\utils\Config;
use poggit\utils\internet\CurlUtils;
use poggit\utils\lang\LangUtils;

class RepoZipball {
    private $file;
    private $zip;
    public $subZipballs = [];
    private $prefix;
    private $prefixLength;

    public function __construct(string $url, string $token, string $apiHead, int $recursion = 0) {
        $this->file = Poggit::getTmpFile(".zip");
        $this->token = $token;
        CurlUtils::curlToFile(CurlUtils::GH_API_PREFIX . $url, $this->file, Config::MAX_ZIPBALL_SIZE, "Authorization: bearer $token");
        CurlUtils::parseGhApiHeaders();
        $this->zip = new \ZipArchive();
        $status = $this->zip->open($this->file);
        if($status !== true) throw new \UnexpectedValueException("Failed opening zip $this->file: $status", $status);
        $this->prefix = $this->zip->getNameIndex(0);
        $this->prefixLength = strlen($this->prefix);
        $this->apiHead = $apiHead;

        if($recursive > 0) $this->parseModules($recursion - 1);
    }

    public function isFile(string $name): bool {
        foreach($this->subZipballs as $dir => $ball) {
            if(LangUtils::startsWith($name, $dir)) {
                return $ball->isFile(substr($name, strlen($dir)));
            }
        }
        return $this->zip->locateName($this->prefix . $name) !== false;
    }

    public function toName(int $index): string {
        return substr($this->zip->getNameIndex($index), $this->prefixLength);
    }

    public function getContentsByIndex(int $index): string {
        return $this->zip->getFromIndex($index);
    }

    public function getContents(string $name): string {
        foreach($this->subZipballs as $dir => $ball) {
            if(LangUtils::startsWith($name, $dir)) {
                return $ball->getContents(substr($name, strlen($dir)));
            }
        }
        return $this->zip->getFromName($this->prefix . $name);
    }

    public function countFiles(): int {
        $cnt = $this->zip->numFiles;
        foreach($this->subZipballs as $ball) $cnt += $ball->countFiles();
        return $cnt;
    }

    public function isDirectory(string $dir): bool {
        $dir = rtrim($dir, "/") . "/";
        foreach($this->subZipballs as $sdir => $ball) {
            if(LangUtils::startsWith($dir, $sdir)) {
                return $ball->isDirectory(substr($dir, strlen($sdir)));
            }
        }
        for($i = 0; $i < $this->zip->numFiles; $i++){
            if(LangUtils::startsWith($this->toName($i), $dir)) return true;
        }
        return false;
    }

    public function iterator(string $pathPrefix = "", bool $callback = false): \Iterator {
        return new class($this, $pathPrefix, $callback) implements \Iterator {
            public function __construct(RepoZipball $zipball, string $pathPrefix, bool $callback = false) {
                $iterators = [$zipball->shallowIterator($pathPrefix, $callback)];
                foreach($zipball->subZipballs as $dir => $subball) $iterators[] = $subball->iterator($pathPrefix . $dir, $callback);
                $this->iteratorIterator = new \ArrayIterator($iterators);
            }

            public function current() {
                return $this->iteratorIterator->current()->current();
            }

            public function next() {
                $this->iteratorIterator->current->next();
                while(!$this->iteratorIterator->current()->valid()) {
                    $this->iteratorIterator->next();
                    if(!$this->iteratorIterator->valid()) return;
                    $this->iteratorIterator->current()->rewind();
                }
            }

            public function key() {
                return $this->iteratorIterator->current()->key();
            }

            public function valid() {
                return $this->iteratorIterator->valid();
            }

            public function rewind() {
                $this->iteratorIterator->rewind();
                if($this->iteratorIterator->valid()) $this->iteratorIterator->current()->rewind();
            }
        };
    }

    public function shallowIterator(string $pathPrefix = "", bool $callback = false): \Iterator {
        return new class($this, $pathPrefix, $callback) implements \Iterator {
            /** @var RepoZipball */
            private $zipball;
            private $pathPrefix;
            private $current;

            public function __construct(RepoZipball $zipball, string $pathPrefix, bool $callback = false) {
                $this->zipball = $zipball;
                $this->pathPrefix = $pathPrefix;
                $this->callback = $callback;
            }

            public function current() {
                return $this->callback ? [$this, "_current"] : $this->_current();
            }

            public function _current() {
                return $this->zipball->getContentsByIndex($this->current);
            }

            public function next() {
                $this->current++;
            }

            public function key() {
                return $this->pathPrefix . $this->zipball->toName($this->current);
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

    public function parseModules(int $levels = 0) { // only supports "normal" .gitmodules files. Not identical implementation as the git-config syntax.
        $str = $this->getContents(".gitmodules");
        if($str === false) return;

        $modules = [];
        $currentModule = null;
        foreach(explode("\n", $str) as $line) {
            if($line{0} === ";" or $line === "#") continue;
            $line = trim($line);
            if(preg_match('/^\[submodule "([^"]+)"\]/$/', $line, $match)) {
                $modules[$match[1]] = $currentModule = new stdClass;
                $currentModule->name = $match[1];
            } elseif($currentModule !== null) {
                $parts = array_map("trim", explode("=", $line, 2));
                if(count($parts) !== 2) continue; // line without equal sign? syntax error? let's ignore it
                $currentModule->{$parts[0]} = $parts[1];
            }
        }

        foreach($modules as $module) {
            if(!isset($module->path, $module->url)) continue; // invalid module! cannot clone!
            if(!preg_match('%^https://([a-zA-Z0-9\-]{1,39}@)?github.com/([^/]+)/([^/]+)(\.git)?$%', $module->url, $urlParts)) continue; // I don't know how to clone non-GitHub repos :(
            list(, , $owner, $repo) = $urlParts;
            $blob = CurlUtils::ghApiGet($this->apiHead . "/contents/$module->path", $this->token);
            if($blob->type === "submodule") {
                $archive = new RepoZipball("repos/$owner/$repo/zipball/$blob->sha", $this->token, "repos/$owner/$repo", $levels);
                $this->subZipballs[rtrim($module->path, "/") . "/"] = $archive;
            }
        }
    }
}
