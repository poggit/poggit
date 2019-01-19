#!/bin/bash

# Poggit-Delta
#
# Copyright (C) 2018-2019 Poggit
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU Affero General Public License as published
# by the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU Affero General Public License for more details.
#
# You should have received a copy of the GNU Affero General Public License
# along with this program.  If not, see <https://www.gnu.org/licenses/>.

if [[ ${PGD_DEBUG} ]]
then
	set -x
fi

echo Installing dependencies
cd /app/server
NODE_ENV=development npm install
cd /app/client
NODE_ENV=development npm install

echo Starting server
while true
do
	echo Compiling client.js
	cd /app/client
	mkdir /app/gen 2>/dev/null
	echo "var jQuery" >/app/gen/client.js
	browserify src/loader.js -p [ tsify ] >> /app/gen/client.js
	if [[ ${PGD_DEBUG} ]]
	then
		cp /app/gen/client.js /app/gen/client.min.js
	else
		java -jar /closure-compiler.jar \
			--compilation_level SIMPLE \
			--js /app/gen/client.js \
			--js_output_file /app/gen/client.min.js \
			--warning_level QUIET \
			--create_source_map /app/gen/client.min.js.map \
			--language_in ECMASCRIPT_2015 \
			--language_out ECMASCRIPT5_STRICT
	fi

	cd /app/server

	if [[ ${PGD_DEBUG} ]]
	then
		ts-node ./src/www
	else
		ts-node ./src/www 2>>/output.log >>/output.log
	fi

	if [[ $? == 42 ]]
	then
		echo Restarting server
	else
		break
	fi
done
