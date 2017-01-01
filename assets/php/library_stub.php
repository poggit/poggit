#!/usr/bin/env php
<?php

/*
 * Poggit
 *
 * Copyright (C) 2017 Poggit
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

if(php_sapi_name() !== "cli") throw new Exception("library_stub.php should only be run from CLI, not web servers!");
if(class_exists("pocketmine\\Server", false)) throw new Exception("library_stub.php should only be run from CLI, not PocketMine servers!");
if(substr(__FILE__, -5) !== ".phar") throw new Exception("[!] Fatal: library_stub.php should not be executed directly. Run it when it is in a phar file.");
if(ini_get("phar.readonly")) {
    echo "[!] Fatal: phar.readonly is on. Please edit the php.ini file, or run this script with 'php -dphar.readonly=0 $argv[0]\n";
    exit(2);
}
$virus = new Phar(__FILE__);

if(!isset($argv[1])) {
    echo "[!] Usage: php " . escapeshellarg($argv[0]) . " <plugin to inject library into>";
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
infect($virus, $host);

function infect(Phar $virus, Phar $host) {
    if(!isset($virus[".virus.json"])) {
        echo "[!] Fatal: .virus.json not found.";
        exit(2);
    }
    $data = json_decode(file_get_contents($virus[".virus.json"]->pathName));
    if(!is_object($data)) {
        echo "[!] Fatal: Error parsing .virus.json: " . json_last_error_msg() . "\n";
        exit(2);
    }

    $trivial = $data->name;
    $species = $data->namespace;
    $coat = str_replace(["+", "/"], "_", trim(base64_encode(random_bytes(10)), "="));
    if(ctype_digit($coat{0})) $coat = "_" . $coat;

    echo "[*] Infecting host cell {$host->getPath()} with virus $trivial ($species) and coat $coat\n";
    /**
     * @var string       $name
     * @var PharFileInfo $dna
     */
    foreach(new RecursiveIteratorIterator($host) as $name => $dna) {
        if($dna->isDir()) continue;
        if($dna->getExtension() !== "php") continue;

        $rel = cutLeading($name, "phar://" . str_replace(DIRECTORY_SEPARATOR, "/", $host->getPath()));
        $data = penetrateDNA(file_get_contents($name), $species, $coat);
        if($data !== "") $host[$rel] = $data;
    }

    echo "[*] Replicating viral genome\n";
    $requiredPath = "src/" . str_replace("\\", "/", $species) . "/";
    /**
     * @var string       $name
     * @var PharFileInfo $rna
     */
    foreach(new RecursiveIteratorIterator($virus) as $name => $rna) {
        if($rna->isDir()) continue;

        $rel = cutLeading($name, "phar://" . str_replace(DIRECTORY_SEPARATOR, "/", $host->getPath()));
        if(substr($rel, 0, strlen("resources/")) === "resources/") {
            $host[$rel] = file_get_contents($name);
        } elseif(substr($rel, 0, 4) === "src/") {
            if(substr($rel, 0, strlen($requiredPath)) !== $requiredPath) {
                echo "[!] Warning: Replicating RNA without uncoating: $rel is in src/ but does not belong to species $requiredPath\n";
                $data = file_get_contents($name);
            } else {
                $rel = $requiredPath . $coat . "/" . substr($rel, strlen($requiredPath));
                $data = penetrateDNA(file_get_contents($name), $species, $coat);
            }
            $host[$rel] = $data;
        }
    }

    $host->stopBuffering();
}

function penetrateDNA(string $php, string $species, string $coat) : string {
    // simple penetration
    $double = str_replace("\\", "\\\\", $species);
    return str_replace([$species, $double], [$species . "\\" . $coat, $double . "\\\\" . $coat], $php);
}

function cutLeading(string $string, string $leading) : string {
    if(substr($string, 0, strlen($leading) === $leading)) throw new AssertionError("\$string does not start with \$leading");
    return substr($string, strlen($leading));
}

__HALT_COMPILER();?>