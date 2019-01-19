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
import {Request, Response} from "express"
import {errorPromise} from "../../../../shared/util"
import {RouteHandler} from "../router"
import {secrets} from "../secrets"
import {SESSION_COOKIE_NAME, SESSION_TIMEOUT} from "./index"
import {Session} from "./Session"
import {createSession, getSession} from "./store"

export const sessionMiddleware: RouteHandler = async(req, res) => {
	[req.sessionId, req.session] = await impl(req, res)
	req.loggedInAs = req.session.loggedIn ? req.session.userId as number : null
	return true
}

async function impl(req: Request, res: Response): Promise<[string, Session]>{
	let cookie = req.cookies[SESSION_COOKIE_NAME]
	if(cookie !== undefined){
		const session = await getSession(cookie)
		if(session !== undefined){
			return [cookie, session]
		}
	}
	cookie = (await errorPromise<Buffer>(cb => crypto.randomBytes(20, cb))).toString("hex")
	req.cookies[SESSION_COOKIE_NAME] = cookie
	res.cookie(SESSION_COOKIE_NAME, cookie, {
		path: "/",
		httpOnly: true,
		maxAge: SESSION_TIMEOUT,
		secure: secrets.domain.startsWith("https://"),
		sameSite: true,
	})
	return [cookie, await createSession(cookie)]
}
