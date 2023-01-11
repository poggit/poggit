<?php /** @noinspection PhpElementIsNotAvailableInCurrentPhpVersionInspection */

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

namespace poggit\virion;

use AssertionError;
use InvalidArgumentException;
use Phar;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use function file_get_contents;
use function is_array;
use function json_decode;
use function json_encode;
use function str_replace;
use function stripos;
use function strlen;
use function strpos;
use function substr;
use function token_get_all;
use function yaml_parse;
use const DIRECTORY_SEPARATOR;
use const PHP_EOL;
use const T_NAMESPACE;
use const T_STRING;
use const T_WHITESPACE;
use const T_NAME_FULLY_QUALIFIED;
use const T_NAME_QUALIFIED;

const VIRION_BUILDER_VERSION = "1.5";

const VIRION_INFECTION_MODE_SYNTAX = 0;
const VIRION_INFECTION_MODE_SINGLE = 1;
const VIRION_INFECTION_MODE_DOUBLE = 2;

echo "Using virion builder: version " . VIRION_BUILDER_VERSION, PHP_EOL;

function virion_infect(Phar $virus, Phar $host, string $prefix = "", int $mode = VIRION_INFECTION_MODE_SYNTAX, &$hostChanges = 0, &$viralChanges = 0): int {
    if(!isset($virus["virion.yml"])) {
        throw new RuntimeException("virion.yml not found, could not activate virion", 2);
    }
    $virionYml = yaml_parse(file_get_contents($virus["virion.yml"]));
    if(!is_array($virionYml)) {
        throw new RuntimeException("Corrupted virion.yml, could not activate virion", 2);
    }
    $srcNamespacePrefix = "";
    if(isset($host["plugin.yml"])) {
        $hostYml = yaml_parse(file_get_contents($host["plugin.yml"]));
        if(!is_array($hostYml)) {
            throw new RuntimeException("Corrupted plugin.yml in host plugin, could not inject virion", 2);
        }
        $host["plugin.yml"] = yaml_emit($hostYml);
        $srcNamespacePrefix = str_replace("\\", "/", $hostYml["src-namespace-prefix"] ?? "");
    }

    $infectionLog = isset($host["virus-infections.json"]) ? json_decode(file_get_contents($host["virus-infections.json"]), true) : [];

    $genus = $virionYml["name"];
    $antigen = $virionYml["antigen"];

    foreach($infectionLog as $old) {
        if($old["antigen"] === $antigen) {
            echo "[!] Target already infected by this virion, aborting\n";
            return 3;
        }
    }

//    do {
//        $antibody = str_replace(["+", "/"], "_", trim(base64_encode(random_bytes(10)), "="));
//        if(ctype_digit($antibody{0})) $antibody = "_" . $antibody;
//        $antibody = $prefix . $antibody . "\\" . $antigen;
//    } while(isset($infectionLog[$antibody]));

    $antibody = $prefix . $antigen;

    $infectionLog[$antibody] = $virionYml;

    echo "Using antibody $antibody for virion $genus ({$antigen})\n";

    $hostPharPath = "phar://" . str_replace(DIRECTORY_SEPARATOR, "/", $host->getPath());
    $hostChanges = 0;
    //Replace all virion namespace references with antibody(injected)namespace references.
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($hostPharPath)) as $name => $chromosome) {
        if($chromosome->isDir()) continue;
        if($chromosome->getExtension() !== "php") continue;

        $rel = cut_prefix($name, $hostPharPath);
        $data = change_dna($original = file_get_contents($name), $antigen, $antibody, $mode, $hostChanges);
        if($data !== "") $host[$rel] = $data;
    }

    $restriction = "src/" . str_replace("\\", "/", $antigen) . "/"; // restriction enzyme ^_^
    $ligase = str_replace("\\", "/", $antibody) . "/";
    if($srcNamespacePrefix !== "" and str_starts_with($ligase, $srcNamespacePrefix)){
        $ligase = cut_prefix($ligase, $srcNamespacePrefix."/");
    }
    $ligase = "src/" . $ligase;

    $viralChanges = 0;
    foreach(new RecursiveIteratorIterator($virus) as $name => $genome) {
        if($genome->isDir()) continue;

        $rel = cut_prefix($name, "phar://" . str_replace(DIRECTORY_SEPARATOR, "/", $virus->getPath()) . "/");

        if(strpos($rel, "resources/") === 0) {
            $host[$rel] = file_get_contents($name);
        } elseif(strpos($rel, "src/") === 0) {
            if(strpos($rel, $restriction) !== 0) {
                echo "Warning: file $rel in virion is not under the antigen $antigen ($restriction)\n";
                $newRel = $rel;
            } else {
                $newRel = $ligase . cut_prefix($rel, $restriction);
            }
            $data = change_dna(file_get_contents($name), $antigen, ($srcNamespacePrefix === "" ? "" : str_replace("/", "\\", $srcNamespacePrefix)."\\").$antibody, $mode, $viralChanges); // it's actually RNA
            $host[$newRel] = $data;
        }
    }

    $host["virus-infections.json"] = json_encode($infectionLog);

    return 0;
}

function cut_prefix(string $string, string $prefix): string {
    if(strpos($string, $prefix) !== 0) throw new AssertionError("\$string does not start with \$prefix:\n$string\n$prefix");
    return substr($string, strlen($prefix));
}

function change_dna(string $chromosome, string $antigen, string $antibody, $mode, &$count = 0): string {
    switch($mode) {
        case VIRION_INFECTION_MODE_SYNTAX:
            $tokens = token_get_all($chromosome);
            $tokens[] = ""; // should not be valid though
            foreach($tokens as $offset => $token) {
                if(!is_array($token) or $token[0] !== T_WHITESPACE) {
                    /** @noinspection IssetArgumentExistenceInspection */
                    list($id, $str, $line) = is_array($token) ? $token : [-1, $token, $line ?? 1];
                    //namespace test; is a T_STRING whereas namespace test\test; is not.
                    if(isset($init, $prefixToken) and $id === T_STRING){
                        if($str === $antigen) { // case-sensitive!
                            $tokens[$offset][1] = $antibody . substr($str, strlen($antigen));
                            ++$count;
                        } elseif(stripos($str, $antigen) === 0) {
                            echo "\x1b[38;5;227m\n[WARNING] Not replacing FQN $str case-insensitively.\n\x1b[m";
                        }
                        unset($init, $prefixToken);
                    } else {
                        if($id === T_NAMESPACE) {
                            $init = $offset;
                            $prefixToken = $id;
                        } elseif($id === T_NAME_QUALIFIED) {
                            if(($str[strlen($antigen)]??"\\") === "\\") {
                                if(strpos($str, $antigen) === 0) { // case-sensitive!
                                    $tokens[$offset][1] = $antibody . substr($str, strlen($antigen));
                                    ++$count;
                                } elseif(stripos($str, $antigen) === 0) {
                                    echo "\x1b[38;5;227m\n[WARNING] Not replacing FQN $str case-insensitively.\n\x1b[m";
                                }
                            }
                            unset($init, $prefixToken);
                        } elseif($id === T_NAME_FULLY_QUALIFIED){
                            if(strpos($str, "\\" . $antigen . "\\") === 0) { // case-sensitive!
                                $tokens[$offset][1] = "\\" . $antibody . substr($str, strlen($antigen)+1);
                                ++$count;
                            } elseif(stripos($str, "\\" . $antigen . "\\") === 0) {
                                echo "\x1b[38;5;227m\n[WARNING] Not replacing FQN $str case-insensitively.\n\x1b[m";
                            }
                            unset($init, $prefixToken);
                        }
                    }
                }
            }
            $ret = "";
            foreach($tokens as $token) {
                $ret .= is_array($token) ? $token[1] : $token;
            }
            break;
        case VIRION_INFECTION_MODE_SINGLE:
            $ret = str_replace($antigen, $antibody, $chromosome, $subCount);
            $count += $subCount;
            break;
        case VIRION_INFECTION_MODE_DOUBLE:
            $ret = str_replace(
                [$antigen, str_replace("\\", "\\\\", $antigen)],
                [$antibody, str_replace("\\", "\\\\", $antibody)],
                $chromosome, $subCount
            );
            $count += $subCount;
            break;
        default:
            throw new InvalidArgumentException("Unknown mode: $mode");
    }

    return $ret;
}
