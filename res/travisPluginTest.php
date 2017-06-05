<?php

/*
 * Copyright 2016-2017 Poggit
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

if(!getenv("TRAVIS")) {
    echo "Only run this script on Travis\n";
    exit(1);
}
$target = $argv[1];
if(!isset($argv[1])) {
    echo /** @lang text */
    "Usage: php $argv[0] <path to download into>\n";
}
list($owner, $repo) = explode("/", getenv("TRAVIS_REPO_SLUG"), 2);
$sha = getenv("TRAVIS_COMMIT");
for($i = 1; true; $i++) {
    echo "Attempting to download CI build from Poggit (trial #$i)\n";
    $json = shell_exec("curl " . escapeshellarg("https://poggit.pmmp.io/ci.info?owner=$owner&repo=$repo&sha=$sha"));
    $data = json_decode($json);
    if($data === null) {
        var_dump($json);
        exit(1);
    }
    if(count($data) === 0) {
        sleep(5);
        echo "[*] Waiting for Poggit builds...\n";
        continue;
    }
    foreach($data as $datum) {
        shell_exec("wget -O " . escapeshellarg($name = $target . $datum->projectName . ".phar") . " " . escapeshellarg("https://poggit.pmmp.io/r/" . $datum->resourceId));
        echo "[*] Downloaded plugin: $name\n";
    }
    exit(0);
}
