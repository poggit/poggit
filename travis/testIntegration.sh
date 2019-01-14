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

cd `dirname "$0"`

exitCode=0

function api-request {
	echo -n "Testing /$2... "
	curl http://localhost/"$2"?format=json > actual.json
	jq -n --argfile expect integration/"$1".json --argfile actual actual.json '$expect == $actual' > result
	cat result
	if [[ `cat result` == "false" ]]
	then
		exitCode=1
		echo "Expected travis/integration/$1.json, got the following:"
		cat actual.json
		echo
		echo
	fi
}

function test-request {
	echo -n "Testing /tests/$2... "
	curl http://localhost/tests/"$2" > actual.json
	jq -n --argfile expect integration/"$1".json --argfile actual actual.json '$expect == $actual' > result
	cat result
	if [[ `cat result` == "false" ]]
	then
		exitCode=1
		echo "Expected travis/integration/$1.json, got the following:"
		cat actual.json
		echo
		echo
	fi
}

test-request root ""

exit ${exitCode}
