/*
 * Poggit-Delta
 *
 * Copyright (C) 2018-2019 Poggit
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

import {app} from "../index"

export function route(){
	app.post("/restart", (req, res) => {
		const address = (req.connection.remoteAddress || "::ffff:8.8.8.8").split(":")
		const digits = address[address.length - 1].split(/\./).map(i => parseInt(i))
		if(
			digits[0] === 172 && 16 <= digits[1] && digits[1] <= 31 || // class B
			digits[0] === 192 && digits[1] === 168 // class C
		){
			res.send("OK\n")
			process.exit(42)
		}else{
			res.send(JSON.stringify(digits))
			res.end()
		}
	})
}
