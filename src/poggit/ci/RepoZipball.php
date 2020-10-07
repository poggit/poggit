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

namespace poggit\ci;

use ArrayIterator;
use Iterator;
use poggit\Config;
use poggit\Meta;
use poggit\utils\internet\Curl;
use poggit\utils\internet\GitHub;
use poggit\utils\internet\GitHubAPIException;
use poggit\utils\lang\Lang;
use stdClass;
use UnexpectedValueException;
use ZipArchive;
use function array_map;
use function count;
use function explode;
use function file_get_contents;
use function json_decode;
use function preg_match;
use function rtrim;
use function strlen;
use function substr;
use function trim;
use function unlink;

class RepoZipball {
    private $file;
    private $token;
    private $zip;
    public $subZipballs = [];
    private $prefix;
    private $prefixLength;
    private $apiHead;

    public function __construct(string $url, string $token, string $apiHead, int &$recursion = 0, string $ref = null, int $maxSize = Config::MAX_ZIPBALL_SIZE) {
        $this->file = Meta::getTmpFile(".zip");
        $this->token = $token;
        Curl::curlToFile(GitHub::GH_API_PREFIX . $url, $this->file, $maxSize, "Authorization: bearer $token");
        Curl::parseHeaders();
        if(Curl::$lastCurlResponseCode >= 400) {
            $data = file_get_contents($this->file);
            $json = json_decode($data);
            if(!($json instanceof stdClass)) {
                $json = (object) [
                    "error" => $data,
                    "documentation_url" => ""
                ];
            }
            throw new GitHubAPIException($url, $json);
        }
        $this->zip = new ZipArchive();
        $status = $this->zip->open($this->file);
        if($status !== true) throw new UnexpectedValueException("Failed opening zip $this->file: $status", $status);
        $this->prefix = $this->zip->getNameIndex(0);
        $this->prefixLength = strlen($this->prefix);
        $this->apiHead = $apiHead;

        $recursion--;
        if($recursion >= 0) $this->parseModules($recursion, $ref);
    }

    public function isFile(string $name): bool {
        foreach($this->subZipballs as $dir => $ball) {
            if(Lang::startsWith($name, $dir)) {
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
            if(Lang::startsWith($name, $dir)) {
                return $ball->getContents(substr($name, strlen($dir)));
            }
        }
        return $this->zip->getFromName($this->prefix . $name);
    }

    public function countFiles(): int {
        $cnt = $this->zip->numFiles;
        foreach($this->subZipballs as $ball) {
            $cnt += $ball->countFiles();
        }
        return $cnt;
    }

    public function isDirectory(string $dir): bool {
        $dir = rtrim($dir, "/") . "/";
        foreach($this->subZipballs as $subDir => $ball) {
            if(Lang::startsWith($dir, $subDir)) {
                return $ball->isDirectory(substr($dir, strlen($subDir)));
            }
        }
        for($i = 0; $i < $this->zip->numFiles; $i++) {
            if(Lang::startsWith($this->toName($i), $dir)) return true;
        }
        return false;
    }

    public function iterator(string $pathPrefix = "", bool $callback = false): Iterator {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return new class($this, $pathPrefix, $callback) implements Iterator {
            private $iteratorIterator;

            public function __construct(RepoZipball $zipball, string $pathPrefix, bool $callback = false) {
                $iterators = [$zipball->shallowIterator($pathPrefix, $callback)];
                foreach($zipball->subZipballs as $dir => $subBall) {
                    $iterators[] = $subBall->iterator($pathPrefix . $dir, $callback);
                }
                $this->iteratorIterator = new ArrayIterator($iterators);
            }

            public function current() {
                return $this->iteratorIterator->current()->current();
            }

            public function next() {
                $this->iteratorIterator->current()->next();
                while(!$this->iteratorIterator->current()->valid()) {
                    $this->iteratorIterator->next();
                    if(!$this->iteratorIterator->valid()) return;
                    $this->iteratorIterator->current()->rewind();
                }
            }

            public function key() {
                return $this->iteratorIterator->current()->key();
            }

            public function valid(): bool {
                return $this->iteratorIterator->valid();
            }

            public function rewind() {
                $this->iteratorIterator->rewind();
                if($this->iteratorIterator->valid()) $this->iteratorIterator->current()->rewind();
            }
        };
    }

    public function shallowIterator(string $pathPrefix = "", bool $callback = false): Iterator {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return new class($this, $pathPrefix, $callback) implements Iterator {
            /** @var RepoZipball */
            private $zipball;
            private $pathPrefix;
            private $currentIndex;
            private $callback;

            public function __construct(RepoZipball $zipball, string $pathPrefix, bool $callback = false) {
                $this->zipball = $zipball;
                $this->pathPrefix = $pathPrefix;
                $this->callback = $callback;
            }

            public function current() {
                $current = $this->currentIndex;
                return $this->callback ? function() use ($current) {
                    return $this->zipball->getContentsByIndex($current);
                } : $this->zipball->getContentsByIndex($this->currentIndex);
            }

            public function next() {
                $this->currentIndex++;
            }

            public function key() {
                return $this->pathPrefix . $this->zipball->toName($this->currentIndex);
            }

            public function valid(): bool {
                return $this->currentIndex < $this->zipball->countFiles();
            }

            public function rewind() {
                $this->currentIndex = 0;
            }
        };
    }

    public function __destruct() {
        $this->zip->close();
        unlink($this->file);
    }

    public function parseModules(int &$levels = 0, string $ref = null) { // only supports "normal" .gitmodules files. Not identical implementation as the git-config syntax.
        $str = $this->getContents(".gitmodules");
        if($str === false) return;

        $modules = [];
        $currentModule = null;
        foreach(explode("\n", $str) as $line) {
            if(!$line or $line[0] === ";" or $line === "#") continue;
            $line = trim($line);
            if(preg_match('/^\[submodule "([^"]+)"\]$/', $line, $match)) {
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
            if(!preg_match('%^https://([a-zA-Z0-9\-]{1,39}@)?github.com/([^/]+)/([^/]+)$%', $module->url, $urlParts)) continue; // I don't know how to clone non-GitHub repos :(
            list(, , $owner, $repo) = $urlParts;
            if(Lang::endsWith($repo, ".git")) $repo = substr($repo, 0, -4);
            $blob = GitHub::ghApiGet($this->apiHead . "/contents/$module->path?ref=$ref", $this->token);
            if($blob->type === "submodule") {
                $archive = new RepoZipball("repos/$owner/$repo/zipball/$blob->sha", $this->token, "repos/$owner/$repo", $levels, null, Meta::getMaxZipballSize("$owner/$repo"));
                $this->subZipballs[rtrim($module->path, "/") . "/"] = $archive;
                if($levels < 0) break;
            }
        }
    }

    public function getZipPath(): string{
        return $this->file;
    }
}
