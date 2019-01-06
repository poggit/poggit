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

import * as crypto from "crypto"
import * as csurf from "csurf"
import * as request from "request-promise-native"
import {NextFunction, Request, RequestHandler, Response} from "express"
import {createSession, getSession} from "./store"
import {errorPromise, parseUrlEncoded} from "../../shared/util"
import {app} from "../index"
import {RouteHandler} from "../router"
import {secrets} from "../secrets"
import {promisify} from "../router/promisify"

export const SESSION_TIMEOUT = 3600 * 1000
export const SESSION_COOKIE_NAME = "PgdSes"

export const sessionMiddleware: RouteHandler = async(req, res) => {
	req.session = await impl(req, res)
	return true
}

async function impl(req: Request, res: Response){
	let cookie = req.cookies[SESSION_COOKIE_NAME]
	if(cookie !== undefined){
		const session = await getSession(cookie)
		if(session !== undefined){
			return session
		}
	}
	cookie = (await errorPromise<Buffer>(cb => crypto.randomBytes(20, cb))).toString("hex")
	req.cookies[SESSION_COOKIE_NAME] = cookie
	res.cookie(SESSION_COOKIE_NAME, cookie, {
		path: "/",
		httpOnly: true,
		maxAge: SESSION_TIMEOUT,
		secure: true,
		sameSite: true,
	})
	return createSession(cookie)
}

const loginCsrf = csurf({
	cookie: true,
	ignoreMethods: [],
	value: req => req.query.state,
})

export function route(){
	app.get("/login", loginCsrf, promisify(loginCallback))

	app.use(((err: any, req: Request, res: Response, next: NextFunction) => {
		if(typeof err === "object" && err.code === "EBadCsrfToken".toUpperCase()){
			promisify(loginRequest)(req, res, next)
			return
		}
		next(err)
	}) as unknown as RequestHandler)
}

const loginCallback: RouteHandler = async(req, res) => {
	const response = await request.post("https://github.com/login/oauth/access_token", {
		form: {
			client_id: secrets.github.oauth.clientId,
			client_secret: secrets.github.oauth.clientSecret,
			code: req.query.code,
			state: req.query.state,
		},
	})
	const {access_token} = parseUrlEncoded(response) as {access_token: string; scope: ""; token_type: "bearer"}

	await req.session.login(access_token)
	res.redirect("/")
}

const loginRequest: RouteHandler = async(req, res) => {
	res.redirectParams("https://github.com/login/oauth/authorize", {
		client_id: secrets.github.oauth.clientId,
		state: req.csrfToken(),
	})
}
