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
import {NextFunction, Request, RequestHandler, Response, Router} from "express"
import {app} from "../index"
import {promisify} from "../router/promisify"
import {secrets} from "../secrets"
import {loginCallback, loginForceCreate, loginRequest} from "./login"
import {logoutCallback, logoutRequest} from "./logout"

export const SESSION_TIMEOUT = 3600 * 1000
export const SESSION_COOKIE_NAME = "PgdSes"

const loginCsrf = csurf({
	cookie: true,
	ignoreMethods: [],
	value: req => req.query.state,
})


export function route(){
	const router = Router()
	router.get("/", loginCsrf, promisify(loginCallback))
	router.use(((err: any, req: Request, res: Response, next: NextFunction) => {
		if(typeof err === "object" && err.code === "EBadCsrfToken".toUpperCase()){
			promisify(loginRequest)(req, res, next)
			return
		}
		next(err)
	}) as unknown as RequestHandler)
	app.use("/login", router)
	if(secrets.test){
		app.get("/tests/login", promisify(loginForceCreate))
	}

	app.get("/logout", promisify(logoutRequest))
	app.post("/logout", promisify(logoutCallback))
}
