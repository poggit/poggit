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
				param: {
					meta: {
						title: "User not found",
						description: `The account ${req.params.username} does not have any information on Poggit`,
					},
					request: {
						type: "user",
						name: req.params.username,
					},
				} as NotFoundRenderParam,
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
			param: {
				meta: {
					title: `@${user.name} on Poggit`,
					description: `@${user.name} has ${user.projects}`,
				},
				name: req.params.username,
			} as UserRenderParam,
		}),
		json: async() => {
			const ret = {
				id: user.id,
				name: user.name,
				isOrg: user.isOrg,
			} as UserApiResult
			if(req.query.projects){
				ret.projects = []
				for(const project of await user.projects){
					const repo = await project.repo
					const p = {
						id: project.id,
						name: project.name,
						repo: {
							id: repo.id,
							name: repo.name,
						},
					} as Exclude<(typeof ret.projects), undefined>[number]
					const release = await project.release
					if(release !== undefined){
						p.released = true
						p.releaseId = release.id
					}else{
						p.released = false
						p.releaseId = null
					}
					ret.projects.push(p)
				}
			}
			if(req.query.releases){

			}
			if(req.query.reviews){

			}
			return ret
		},
	})
}
