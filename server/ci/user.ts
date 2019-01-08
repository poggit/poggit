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

import {UserApiResult} from "../../shared/api/ci/UserApiResult"
import {ErrorApiResult} from "../../shared/api/ErrorApiResult"
import {SessionInfo} from "../../view"
import {NotFoundRenderParam} from "../../view/ci/notFound.view"
import {UserRenderParam} from "../../view/ci/user.view"
import {db} from "../db"
import {User} from "../model/gh/User"
import {RouteHandler} from "../router"

export const userHandler: RouteHandler = async(req, res) => {
	const user = await db.getRepository(User).findOne({name: req.params.username}, {})
	if(user === undefined){
		res.status(404)
		await res.mux({
			html: () => ({
				name: "error",
				param: new NotFoundRenderParam({
					title: "User not found",
					description: `The account ${req.params.username} does not have any information on Poggit`,
				}, SessionInfo.create(req), "user", req.params.username),
			}),
			json: () => ({
				error: "UserNotFound",
			} as ErrorApiResult),
		})
		return
	}
	await res.mux({
		html: () => ({
			name: "ci/user",
			param: new UserRenderParam({
				title: `@${user.name} on Poggit`,
				description: `@${user.name} has ${user.projects}`,
			}, SessionInfo.create(req)),
		}),
		json: () => ({
			id: user.id,
			name: user.name,
			isOrg: user.isOrg,
		} as UserApiResult),
	})
}
