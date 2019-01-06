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

import * as cookieParser from "cookie-parser"
import * as express from "express"
import {RequestHandler} from "express"
import * as morgan from "morgan"
import * as path from "path"
import * as sass from "node-sass-middleware"
import * as db from "./db"
import {route} from "./router"
import {secrets} from "./secrets"
import {INSTALL_DIR} from "./setup"
import {errorHandler} from "./error"
import {logger} from "../shared/console"

export const app = express()

// noinspection JSUnusedGlobalSymbols
export const ready = async() => {
	if(secrets.debug){
		logger.warn("Debug mode is enabled!")
	}

	await db.init()

	app.set("views", path.join(INSTALL_DIR, "view"))
	app.set("view engine", "pug")

	app.use(morgan("dev"))
	app.use(cookieParser())

	app.use(sass({
		src: path.join(INSTALL_DIR, "sass"),
		dest: path.join(INSTALL_DIR, "gen"),
		indentedSyntax: true,
		sourceMap: true,
		outputStyle: secrets.debug ? "nested" : "compressed",
		sourceComments: false,
	}))

	app.use(express.static(path.join(INSTALL_DIR, "public")))
	app.use(express.static(path.join(INSTALL_DIR, "gen")))
	app.use(express.static(path.join(INSTALL_DIR, "node_modules", "bootstrap-sass", "assets")))

	if(secrets.debug){
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

	route()

	app.use(errorHandler as unknown as RequestHandler)

	return app
}
