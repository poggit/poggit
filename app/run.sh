#!/bin/bash

start=`date +%s`
#set -x

cd /main
bash ./lib/link.sh
bash ./link.sh

end=`date +%s`
echo "Startup took $((end-start)) seconds"

while true; do
	start=`date +%s`
	cd /main/client
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
	end=`date +%s`
	echo "Loop cycle took $((end-start)) seconds"

	cd /main/server
	ts-node src
	EXIT_CODE=$?
	if [[ $EXIT_CODE -ne 42 ]]; then
			break
	fi
done
