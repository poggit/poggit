#!/usr/bin/env php
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

use const poggit\virion\VIRION_INFECTION_MODE_SYNTAX;

define("VIRION_MODEL_VERSION", 1);

if(PHP_SAPI !== "cli") {
    echo "virion_stub.php should only be run from CLI, not web servers!\n";
    exit(1);
}
if(class_exists("pocketmine\\Server", false)) {
    echo "virion_stub.php should be run from CLI directly, not PocketMine servers!\n";
    exit(1);
}
if(!Phar::running()) {
    echo "[!] Fatal: virion_stub.php should not be executed directly. Run it when it is in a phar file.\n";
    exit(1);
}
if(ini_get("phar.readonly")) {
    echo "[!] Fatal: phar.readonly is on. Please edit the php.ini file, or run this script with 'php -dphar.readonly=0 $argv[0]\n";
    exit(1);
}

$cliMap = [];
if(is_file(Phar::running() . "/cli-map.json")) {
    $cliMap = json_decode(file_get_contents(Phar::running() . "/cli-map.json"), true);
}

if(!isset($argv[1])) {
    echo "[!] Usage: php " . escapeshellarg($argv[0]) . " " . implode("|", array_merge(array_keys($cliMap), ["<plugin phar>"])) . "\n";
    exit(2);
}

if(substr($argv[1], -5) !== ".phar") {
    if(isset($cliMap[$argv[1]])) {
        exit (require Phar::running() . "/" . $cliMap[$argv[1]]);
    }
}

require Phar::running() . "/virion.php";
if(!function_exists('poggit\virion\virion_infect')) {
    echo "[!] Fatal: virion.php does not exist in this phar!\n";
    exit(1);
}

$virus = new Phar(Phar::running(false));

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
    $status = poggit\virion\virion_infect($virus, $host, $argv[2] ?? ("_" . bin2hex(random_bytes(10))), VIRION_INFECTION_MODE_SYNTAX, $hostChanges, $viralChanges);
    echo "Shaded $hostChanges references in host and $viralChanges references in virion.\n";
    if($status !== 0) exit($status);
} catch(RuntimeException $e) {
    echo "[!] {$e->getMessage()}\n";
    exit($e->getCode());
}
$host->stopBuffering();

echo "[*] Infected $argv[1] with " . Phar::running(false) . PHP_EOL;
exit(0);
