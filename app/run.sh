#!/bin/bash

cd /main
cp -r base/* .
bash /main/lib/link.sh

cd client
npm link poggit-eps-lib-frontend
npm install

mkdir gen 2>/dev/null

browserify src/loader.js -p [ tsify ] >> gen/client.js
if [[ $POGGIT_DEBUG ]]; then
	cp gen/client.js gen/client.min.js
else
	java -jar /closure-compiler.jar \
		--compilation_level SIMPLE \
		--js /app/gen/client.js \
		--js_output_file gen/client.min.js \
		--warning_level QUIET \
		--create_source_map gen/client.min.js.map \
		--language_in ECMASCRIPT_2015 \
		--language_out ECMASCRIPT5_STRICT
fi

cd ../server
npm link poggit-eps-lib-server
npm link poggit-eps-lib-frontend
npm install
ts-node src
