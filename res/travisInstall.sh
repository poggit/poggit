#!/bin/bash
set -e
if [ "$TRAVIS" != "true" ]; then
    echo Please only run this script on Travis-CI
    exit 1
fi

echo Installing pthreads 3.1.6
pecl install channel://pecl.php.net/pthreads-3.1.6
echo Installing weakref 0.3.2
pecl install channel://pecl.php.net/weakref-0.3.2
echo Installing yaml 2.0.0-RC7
echo | pecl install channel://pecl.php.net/yaml-2.0.0RC7

mkdir "$TRAVIS_BUILD_DIR"/../PocketMine && cd "$TRAVIS_BUILD_DIR"/../PocketMine
echo Installing PocketMine in $PWD
echo Downloading PocketMine build from Poggit
wget -O PocketMine-MP.phar https://poggit.pmmp.io/get.pmmp/master
mkdir plugins && wget -O plugins/PluginChecker.phar https://poggit.pmmp.io/res/PluginChecker.phar

echo Downloading Poggit build
wget -O - https://poggit.pmmp.io/res/travisPluginTest.php | php -- plugins/test-subject.phar

echo Installed allthethings. Execute https://poggit.pmmp.io/travisScript.sh in the script phase to execute test.
exit 0
