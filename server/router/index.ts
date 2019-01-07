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

import * as csurf from "csurf"
import {app} from ".."
import {SessionInfo} from "../../view"
import {ErrorRenderParam} from "../../view/error.view"
import * as ci from "../ci"
import {PoggitRequest, PoggitResponse} from "../ext"
import {homeHandler} from "../home"
import {tosController} from "../home/tos"
import * as session from "../session"
import {sessionMiddleware} from "../session"
import {utilMiddleware} from "../util/middleware"
import {promisify} from "./promisify"

const csrfMiddleware = csurf({
	value: (req: PoggitRequest) => req.getHeader("x-csrf") || "",
	cookie: true,
})

export function route(){
	app.use(promisify(utilMiddleware))

	app.get("/tos", promisify(tosController))

	app.use(promisify(sessionMiddleware))

	app.use(csrfMiddleware)

	app.get("/", promisify(homeHandler))
	session.route()
	ci.route()

	app.use(promisify(notFoundHandler))
}

const notFoundHandler: RouteHandler = async(req, res) => {
	res.status(404)
	await res.mux({
		html: () => ({
			name: "error",
			param: new ErrorRenderParam({
					title: "404 Not Found",
					description: `Page ${req.path} not found`,
				}, SessionInfo.create(req as PoggitRequest),
				`Redirected from: ${req.getHeader("referer")}`),
		}),
		json: () => ({
			"error": "404 Not Found",
		}),
	})
}

export type RouteHandler = (req: PoggitRequest, res: PoggitResponse) => Promise<boolean | void>
