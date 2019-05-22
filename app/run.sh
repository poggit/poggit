#!/bin/bash

set -x

cd /main
cp -r base/* .
find lib -type l -exec rm {} +
bash ./lib/link.sh
bash ./link.sh

cd client
[[ -d /main/gen ]] || mkdir /main/gen
browserify src/loader.js -p [ tsify ] >> /main/gen/client.js
if [[ $POGGIT_DEBUG ]]; then
	cp /main/gen/client.js /main/gen/client.min.js
else
	java -jar /closure-compiler.jar \
		--compilation_level SIMPLE \
		--js /main/gen/client.js \
		--js_output_file /main/gen/client.min.js \
		--warning_level QUIET \
		--create_source_map /main/gen/client.min.js.map \
		--language_in ECMASCRIPT_2015 \
		--language_out ECMASCRIPT5_STRICT
fi

cd ../server

ts-node src
