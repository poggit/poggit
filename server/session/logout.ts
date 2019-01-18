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

import {PoggitError} from "../../shared/PoggitError"
import {LogoutRenderParam} from "../../view/session/logout.view"
import {RouteHandler} from "../router"

export const logoutRequest: RouteHandler = async(req, res) => {
	if(!req.session.loggedIn){
		throw PoggitError.friendly("AlreadyLoggedOut", "You were not logged in")
	}

	await res.mux({
		html: () => ({
			name: "session/logout",
			param: {
				meta: {
					title: "Confirm logout",
					description: "Confirm that you want to logout"
				}
			} as LogoutRenderParam
		})
	})
}

export const logoutCallback: RouteHandler = async(req, res) => {
	await req.session.logout()
	res.redirect("/")
}
