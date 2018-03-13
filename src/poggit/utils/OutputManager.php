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

namespace poggit\utils;

use Gajus\Dindent\Indenter;
use poggit\Meta;
use RuntimeException;
use function ob_end_clean;
use function ob_end_flush;
use function ob_flush;
use function ob_get_length;
use function ob_start;

class OutputManager {
    public static $root;
    public static $tail;
    public static $plainTextOutput = false;
    private static $nextId = 0;

    private $id;
    /** @var OutputManager|null */
    private $parent;
    /** @var OutputManager|null */
    private $child = null;

    /** @var string */
    private $buffer = "";

    public function __construct(OutputManager $parent = null) {
        $this->parent = $parent;
        $this->id = self::$nextId++;
        self::$tail = $this;

        if($parent === null and self::$root === null) {
            self::$root = $this;
            ob_start([$this, "handle"], 1024);
        }
    }

    public static function startMinifyHtml(): OutputManager {
        return self::$tail->startChild();
    }

    public static function endMinifyHtml(OutputManager $minifier) {
        ob_flush();
        $minifier->processedOutput(function($html) {
            return Meta::$debugIndent ? (new Indenter([
                "indentation_character" => " "
            ]))->indent($html) : $html;
        });
    }

    public function startChild(): OutputManager {
        if($this->child !== null) {
            return $this->child->startChild();
        }
        $this->child = new self($this);

        return $this->child;
    }

    public function handle(string $buffer) {
        if($this->child !== null) {
            $this->child->handle($buffer);
            return;
        }
        $this->append($buffer);
    }

    public function flush() {
        ob_flush();
        if($this->parent === null) {
            ob_end_clean();
            echo $this->buffer;
            ob_start([$this, "handle"]);
        } else {
            $this->parent->append($this->buffer);
        }
        $this->buffer = "";
    }

    public function output() {
        if($this->child !== null) {
            throw new RuntimeException("Cannot close output manager with child");
        }
        if($this->parent === null) {
            if(ob_get_length()) {
                ob_end_clean();
            } else ob_end_flush();
            echo $this->buffer;
        } else {
            $this->parent->closeChild($this->buffer);
        }
    }

    public function outputTree() {
        $this->output();
        if($this->parent !== null) {
            $this->parent->outputTree();
        }
    }

    public function processedOutput(callable $processor) {
        $this->buffer = $processor($this->buffer);
        $this->output();
    }

    public function terminateGet(): string {
        if($this->child !== null) {
            $this->child->flush();
            $this->child = null;
        } else ob_flush();
        $ret = $this->buffer;
        $this->parent->closeChild("");
        return $ret;
    }

    public function terminate() {
        if($this->parent === null) {
            echo "\0"; // hack
            ob_end_clean();
        } else {
            $this->parent->closeChild("");
        }
    }

    public static function terminateAll(): bool {
        if(self::$tail !== null) {
            self::$tail->terminateTree();
            return true;
        }
        return false;
    }

    private function terminateTree() {
        if($this->parent !== null) {
            $this->parent->terminateTree();
            return;
        }
        $this->terminate();
    }

    protected function closeChild(string $buffer) {
        $this->append($buffer);
        $this->child = null;
        self::$tail = $this;
    }

    protected function append(string $buffer) {
        $this->buffer .= $buffer;
    }

    public function getId(): int {
        return $this->id;
    }
}
