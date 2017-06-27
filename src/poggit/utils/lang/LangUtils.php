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

namespace poggit\utils\lang;

use Composer\Semver\Semver;
use Gajus\Dindent\Indenter;
use mysqli;
use poggit\debug\DebugModule;
use poggit\errdoc\GitHubTimeoutErrorPage;
use poggit\errdoc\InternalErrorPage;
use poggit\Meta;
use poggit\utils\internet\CurlTimeoutException;
use poggit\utils\OutputManager;
use ZipArchive;

class LangUtils {
    public static function startsWith(string $string, string $prefix): bool {
        return strlen($string) >= strlen($prefix) and substr($string, 0, strlen($prefix)) === $prefix;
    }

    public static function endsWith(string $string, string $suffix): bool {
        return strlen($string) >= strlen($suffix) and substr($string, -strlen($suffix)) === $suffix;
    }

    public static function copyToObject($source, $object) {
        foreach($source as $k => $v) {
            $object->{$k} = $v;
        }
    }

    public static function handleError(\Throwable $ex) {
        http_response_code(500);
        $refid = Meta::getRequestId();

        if(Meta::hasLog()) {
            Meta::getLog()->e(LangUtils::exceptionToString($ex));
            if(OutputManager::$plainTextOutput) {
                header("Content-Type: text/plain");
                if(Meta::isDebug()) {
                    OutputManager::$current->outputTree();
                } else {
                    OutputManager::terminateAll();
                }
                echo "Request#$refid\n";
            } else {
                OutputManager::terminateAll();
                if($ex instanceof CurlTimeoutException) {
                    http_response_code(524);
                    (new GitHubTimeoutErrorPage(""))->output();
                } else {
                    (new InternalErrorPage((string) $refid))->output();
                }
            }
        } else {
            header("Content-Type: text/plain");
            OutputManager::terminateAll();
            echo "Request #$refid\n";
            if(DebugModule::isTester()) echo LangUtils::exceptionToString($ex);
        }

        die;
    }

    public static function myShellExec(string $cmd, &$stdout, &$stderr = null, &$exitCode = null, $cwd = null) {
        $proc = proc_open($cmd, [
            1 => ["pipe", "w"],
            2 => ["pipe", "w"]
        ], $pipes, $cwd ?? getcwd());
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = (int) proc_close($proc);
    }

    public static function checkDeps() {
        if(!(function_exists("ast\\parse_code"))) throw new \AssertionError("Missing extension: \"ast\"");
        if(!(function_exists("apcu_store"))) throw new \AssertionError("Missing extension: \"APCu\"");
        if(!(!ini_get("phar.readonly"))) throw new \AssertionError("Invalid configuration: \"phar\"");
        if(!(function_exists("curl_init"))) throw new \AssertionError("Missing exxtension: \"curl\"");
        if(!(function_exists("getimagesizefromstring"))) throw new \AssertionError("Missing extension: \"gd\"");
        if(!(class_exists(ZipArchive::class))) throw new \AssertionError("Missing extension: \"ZipArchive\"");
        if(!(class_exists(mysqli::class))) throw new \AssertionError("Missing extension: \"mysqli\"");
        if(!(function_exists("yaml_emit"))) throw new \AssertionError("Missing extension: \"yaml\"");
        if(!(class_exists(Indenter::class))) throw new \AssertionError("Missing library: \"dindent\"");
        if(!(class_exists(Semver::class))) throw new \AssertionError("Missing library: \"semver\"");
    }

    public static function exceptionToString(\Throwable $ex) {
        return get_class($ex) . ": " . $ex->getMessage() . " in " . $ex->getFile() . ":" . $ex->getLine() . "\n" . $ex->getTraceAsString();
    }
}
