#!/bin/bash
if [ "$1" == "" ]; then
    echo Usage: travisScript.sh '<name of plugin to be tested> <name of project to be tested>'
    exit 2
fi
PLUGIN_NAME="$1"
cd "$TRAVIS_BUILD_DIR"/../PocketMine

if [ "$2" == "" ]; then
    PROJECT_NAME="$1"
else
    PROJECT_NAME="$2"
fi

echo Staging "$PROJECT_NAME".phar
cp unstaged/"$PROJECT_NAME".phar plugins/"$PROJECT_NAME".phar || (echo "Project $PROJECT_NAME is not built in this commit" && exit 0)

echo Loading .travis.pmcommands.txt
pmcommands_file="$TRAVIS_BUILD_DIR"/.travis.pmcommands.txt
if [ ! -f "$pmcommands_file" ]; then
    echo version >> "$pmcommands_file"
    echo check-plugins >> "$pmcommands_file"
fi
egrep "^stop\$" "$pmcommands_file" || (echo stop >> "$pmcommands_file" && echo >> "$pmcommands_file")

cmds_to_run="$(cat "$pmcommands_file")"
echo Running the following commands:
echo ===
echo "$cmds_to_run"
echo ===

echo Server plugins directory:
ls plugins/*.phar
php PocketMine-MP.phar --no-wizard --enable-ansi --settings.asyncworker=2 --debug.level=2 --debug.commands=true --disable-readline --pluginchecker.target="$PLUGIN_NAME" < "$pmcommands_file" | tee stdout.log

rm plugins/"$PROJECT_NAME".phar

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
