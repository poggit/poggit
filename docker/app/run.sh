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

if [[ $PGD_DEBUG ]]
then
	set -x
fi

cd /home/node/ex
echo Copying ro to ex
cp -r /home/node/ro/* . || true
echo Installing dependencies
NODE_ENV=development npm install
cd server

function copy-dir {
	rm -r "$1"
	cp -r /home/node/ro/"$1" "$1"
}

echo Starting server
while true
do
	cd ..
	copy-dir client
	copy-dir public
	copy-dir sass
	copy-dir secrets
	copy-dir server
	copy-dir shared
	copy-dir view

	echo Compiling client.js
	(cd client && tsc)
	echo "(function(define, require, requirejs){" > gen/client/main.debug.js
	cat gen/client/modules.js >> gen/client/main.debug.js
	cat client/loader.js >> gen/client/main.debug.js
	echo >> gen/client/main.debug.js
	echo "})(define, require, requirejs)" >> gen/client/main.debug.js

	if [[ $PGD_DEBUG ]]
	then
		echo Not minifying client.js
		cp gen/client/main.debug.js gen/client/main.js
	else
		echo Minifying client.js

		java -jar /home/node/closure-compiler.jar \
				--compilation_level SIMPLE \
				--js gen/client/main.debug.js \
				--js_output_file gen/client/main.js \
				--language_in ECMASCRIPT_2015 \
				--language_out ECMASCRIPT3
	fi

	cd server

	if [[ $PGD_DEBUG ]]
	then
		ls ..
		ls ../shared
		ts-node ../bin/www
	else
		ts-node ../bin/www 2>>../output.log >> ../output.log
	fi

	if [[ $? == 42 ]]
	then
		echo Restarting server
	else
		break
	fi
done
