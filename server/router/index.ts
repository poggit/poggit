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
import * as session from "../session"
import {sessionMiddleware} from "../session"
import * as ci from "../ci"
import {app} from ".."
import {PoggitRequest, PoggitResponse} from "../ext"
import {ErrorRenderParam} from "../../view/error.view"
import {homeHandler} from "../home"
import {SessionInfo} from "../../view"
import {secrets} from "../secrets"
import {utilMiddleware} from "../util/middleware"
import {promisify} from "./promisify"

const csrfMiddleware = csurf({
	value: (req: PoggitRequest) => req.getHeader("x-csrf") || "",
	cookie: true,
})

export function route(){
	app.use(promisify(utilMiddleware))
	app.use(promisify(sessionMiddleware))

	if(secrets.debug){
		app.get("/init-error", promisify(async(req, res) => {
			throw new Error("GHI")
		}))
	}

	app.use(csrfMiddleware)

	app.get("/", promisify(homeHandler))
	session.route()
	ci.route()

	app.use(promisify(async(req, res) => {
		res.status(404)
		await res.pug("error", new ErrorRenderParam({
				title: "404 Not Found",
				description: `Page ${req.path} not found`,
			}, SessionInfo.create(req as PoggitRequest),
			`Redirected from: ${req.getHeader("referer")}`))
	}))
}

export type RouteHandler = (req: PoggitRequest, res: PoggitResponse) => Promise<boolean | void>
