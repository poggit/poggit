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

import * as http from "http"
import * as path from "path"
import * as touch from "touch"
import {ready} from "."
import {logger} from "../../../shared/console"

const PORT = 3000

ready().then(async app => {
	app.set("port", PORT)
	const server = http.createServer(app)
	server.listen(PORT)
	await new Promise((resolve, reject) => {
		server.on("error", reject)
		server.on("listening", resolve)
	})
	logger.info(`Server started! Listening on port ${PORT}`)
	await touch(path.join(__dirname, "..", ".server_started"), {})
}).catch(err => {
	switch(err.code){
		case "EADDRINUSE":
			logger.error(`Port ${PORT} is already in use`)
			process.exit(1)
			return
		default:
			try{
				logger.error(err)
			}catch(err2){
				console.error(err2)
				process.exit(2)
			}
	}
})
