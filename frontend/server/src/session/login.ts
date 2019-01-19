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

import * as qs from "qs"
import * as request from "request-promise-native"
import {RouteHandler} from "../router"
import {secrets} from "../secrets"

export const loginRequest: RouteHandler = async(req, res) => {
	res.redirectParams("https://github.com/login/oauth/authorize", {
		client_id: secrets.github.oauth.clientId,
		state: req.csrfToken(),
	})
}

export const loginCallback: RouteHandler = async(req, res) => {
	// qs.parse()
	const response = await request.post("https://github.com/login/oauth/access_token", {
		form: {
			client_id: secrets.github.oauth.clientId,
			client_secret: secrets.github.oauth.clientSecret,
			code: req.query.code,
			state: req.query.state,
		},
	})
	const {access_token} = qs.parse(response, {depth: 0}) as {access_token: string; scope: ""; token_type: "bearer"}

	await req.session.login(access_token)
	res.redirect("/")
}

export const loginForceCreate: RouteHandler = async(req, res) => {
	const accessToken = req.query.token
	await req.session.login(accessToken)
	res.redirect("/tests/sessionInfo")
}
