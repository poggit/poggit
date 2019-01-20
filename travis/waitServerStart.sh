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

WAIT_LOOPS=0
echo "Waiting for frontend server..."
while [[ `stat -c %Y .server_started` -eq `cat .server_start_after` ]]
do
	sleep 1
	((WAIT_LOOPS++))
	[[ ${WAIT_LOOPS} -ge 120 ]] && exit 1
done
