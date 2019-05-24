#!/bin/bash

start=`date +%s`
#set -x

cd /main
bash ./lib/link.sh
bash ./link.sh

end=`date +%s`
echo "Startup took $((end-start)) seconds"

while true; do
	ts-node src
	EXIT_CODE=$?
	if [[ $EXIT_CODE -ne 42 ]]; then
			break
	fi
done
