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

import {randomBytes} from "crypto"
import {NextFunction, Request, Response} from "express"
import {app} from "./index"
import {userHandler} from "./ci/user"
import {projectHandler} from "./ci/project"
import {PoggitRequest, PoggitResponse} from "./ext"
import {errorPromise} from "../shared/util"
import {ErrorRenderParam} from "../view/error.view"
import {homeHandler} from "./home"
import {error} from "../shared/error"
import {RenderParam} from "../view"

export function promisify(fn: RouteHandler){
	return (req: Request, res: Response, next: NextFunction) => {
		(async() => {
			let input = req as PoggitRequest
			input = Object.assign(input, {
				getHeader: (name: string): string | undefined => {
					const ret = req.headers[name.toLowerCase()]
					if(typeof ret === "string"){
						return ret
					}
					if(typeof ret !== "object"){
						return undefined
					}
					return ret[0]
				},
				getHeaders: (name: string): string[] => {
					const ret = req.headers[name.toLowerCase()]
					if(typeof ret === "string"){
						return [ret]
					}
					if(typeof ret !== "object"){
						return []
					}
					return ret
				},
			})
			input.requestId = input.getHeader("cf-ray") ||
				(await errorPromise<Buffer>(cb => randomBytes(8, cb))).toString("hex")
			const output = Object.assign(res, {
				pug: async function(this: Response, name: string, param: RenderParam){
					param.meta.url = param.meta.url || `https://poggit.pmmp.io${req.path}`
					const html = await errorPromise<string>(cb => this.render(name, param, cb))
					this.send(html)
					return html
				},
			}) as PoggitResponse
			return await fn(input, output)
		})()
			.then(cont => cont === true ? next() : void 0)
			.catch((err: error) => next(err))
	}
}

export function route(){
	app.get("/", promisify(homeHandler))
	app.get("/@:username", promisify(userHandler))
	app.get("/@:username/project", promisify(projectHandler))

	app.use(promisify(async(req, res) => {
		res.status(404)
		await res.pug("error", new ErrorRenderParam({
			title: "404 Not Found",
			description: `Page ${req.path} not found`,
		}, `Redirected from: ${req.getHeader("referer")}`))
	}))
}

export type RouteHandler = (req: PoggitRequest, res: PoggitResponse) => Promise<boolean | void>
