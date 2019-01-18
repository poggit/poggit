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

exitCode=0

function post-test {
	jq -n --argfile expect integration/"$1".json --argfile actual actual.json '$expect == $actual' > result
	if [[ `cat result` == "false" ]]
	then
		exitCode=1
		echo "Test failed: Expected travis/integration/$1.json, got the following:"
		cat actual.json
		echo
		echo
	else
		echo "Test passed."
	fi
}

function api-request {
	echo -n "Testing /$2... "
	curl -LSs -H "Accept: application/json" http://localhost/"$2" > actual.json
	post-test "$1"
}

function test-request {
	echo -n "Testing /tests/$2... "
	curl -LSs http://localhost/tests/"$2" > actual.json
	post-test "$1"
}
