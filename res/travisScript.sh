#!/bin/bash
if [ "$1" == "" ]; then
    echo Usage: travisScript.sh '<name of plugin to be tested>'
    exit 2
fi
cd "$TRAVIS_BUILD_DIR"/../PocketMine

pmcommands_file="$TRAVIS_BUILD_DIR"/.travis.pmcommands.txt
if [ ! -f "$pmcommands_file" ]; then
    echo version >> "$pmcommands_file"
    echo check-plugins >> "$pmcommands_file"
fi
echo stop >> "$pmcommands_file"
echo >> "$pmcommands_file"

cmds_to_run="$(cat "$pmcommands_file")"
echo Running the following commands:
echo "$cmds_to_run"
php PocketMine-MP.phar --no-wizard --enable-ansi --debug.level=2 --debug.commands=true --disable-readline --pluginchecker.target="$1" < "$pmcommands_file" | tee stdout.log
if grep "PluginChecker passed" stdout.log >/dev/null; then
    if grep "PluginChecker disabled fluently" stdout.log >/dev/null; then
        echo Test passed
        exit 0
    else
        echo Server did not shutdown normally
        exit 1
    fi
else
    echo Plugin could not be loaded
    exit 1
fi
