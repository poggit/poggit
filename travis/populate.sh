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

function docker-mysql {
	docker exec -i `docker-compose ps -q mysql` \
			bash -c 'mysql -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' \
			3>&1 1>&2- 2>&3- | \
		grep -v 'Using a password on the command line interface can be insecure'
}

function execute {
	buffer=""
	while IFS='' read -r line || [[ -n "$line" ]]; do
		if echo "$line" | grep -qE '^--'; then
			continue
		fi
		buffer="${buffer}${line}\n"
		if echo "$line" | grep -qE ';$'; then
			(>&2 echo -e "QUERY: $buffer")
			echo -e "$buffer" | docker-mysql
			buffer=""
		fi
	done < "$1"
}

execute ./populate/api_version.sql
execute ./populate/submit_rule.sql
execute ./populate/user.sql
