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
import {NextFunction, Request, RequestHandler, Response} from "express"
import * as morgan from "morgan"
import * as path from "path"
import * as sass from "node-sass-middleware"
import * as db from "./db"
import {route} from "./router"
import {secrets} from "./secrets"
import {INSTALL_DIR} from "./setup"
import {ErrorRenderParam} from "../view/error.view"
import {error} from "../shared/error"

export const app = express()

// noinspection JSUnusedGlobalSymbols
export const ready = async() => {
	if(secrets.debug){
		console.warn("Debug mode is enabled!")
	}

	await db.init()

	app.set("views", path.join(INSTALL_DIR, "view"))
	app.set("view engine", "pug")

	app.use(morgan("dev"))
	app.use(cookieParser())

	app.use(sass({
		src: path.join(INSTALL_DIR, "sass"),
		dest: path.join(INSTALL_DIR, "gen", "style"),
		indentedSyntax: true,
		sourceMap: true,
		outputStyle: "compressed",
		sourceComments: false,
	}))

	app.use(express.static(path.join(INSTALL_DIR, "public")))
	app.use(express.static(path.join(INSTALL_DIR, "gen")))
	app.use(express.static(path.join(INSTALL_DIR, "node_modules", "bootstrap-sass", "assets")))

	route()

	// noinspection JSUnusedLocalSymbols
	app.use(((err: error, req: Request, res: Response, next: NextFunction) => {
		if(!err.friendly){
			console.error(err.toString())
		}
		res.status(500)
		res.render("error", new ErrorRenderParam({
			title: "Internal server error",
			description: err.friendly ? err.message : "A 500 ISE occurred.",
			url: `https://poggit.pmmp.io${req.path}`,
		}, `Request #${(req as any).requestId || "????????"}\n${err.friendly ? err.details : ""}`))
	}) as unknown as RequestHandler)

	return app
}
