#!/usr/bin/env php
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

define("VIRION_MODEL_VERSION", 1);

if(php_sapi_name() !== "cli") {
    echo("virion_stub.php should only be run from CLI, not web servers!");
    exit(1);
}
if(class_exists("pocketmine\\Server", false)) {
    echo("virion_stub.php should only be run from CLI, not PocketMine servers!");
    exit(1);
}
if(substr(__FILE__, -5) !== ".phar") {
    echo "[!] Fatal: virion_stub.php should not be executed directly. Run it when it is in a phar file.";
    exit(1);
}
if(ini_get("phar.readonly")) {
    echo "[!] Fatal: phar.readonly is on. Please edit the php.ini file, or run this script with 'php -dphar.readonly=0 $argv[0]\n";
    exit(1);
}

require "phar://" . __FILE__ . "/virion.php";
if(!function_exists('poggit\virion\virion_infect')) {
    echo "[!] Fatal: virion.php does not exist in this phar!\n";
    exit(1);
}

$virus = new Phar(__FILE__);

if(!isset($argv[1])) {
    echo "[!] Usage: php " . escapeshellarg($argv[0]) . " <plugin phar to inject library into>";
    exit(2);
}

if(!is_file($argv[1])) {
    echo "[!] Fatal: No such file or directory: $argv[1]\n";
    exit(2);
}
if(!is_readable($argv[1])) {
    echo "[!] Fatal: $argv[1] is not a readable file!\n";
    exit(2);
}
if(!is_writable($argv[1])) {
    echo "[!] Fatal: $argv[1] is not a writable file!\n";
    exit(2);
}

$host = new Phar($argv[1]);
$host->startBuffering();

try {
    poggit\virion\virion_infect($virus, $host, isset($argv[2]) and $argv[2] === "-d");
} catch(RuntimeException $e) {
    echo "[!] {$e->getMessage()}\n";
    exit($e->getCode());
}

echo "[*] Infected $argv[1] with " . __FILE__ . PHP_EOL;
exit(0);

__HALT_COMPILER();