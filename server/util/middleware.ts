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

import {RouteHandler} from "../router"
import {Request, Response} from "express"
import {emitUrlEncoded, errorPromise} from "../../shared/util"
import {randomBytes} from "crypto"
import {RenderParam} from "../../view"
import {secrets} from "../secrets"
import {logger} from "../../shared/console"

export const utilMiddleware: RouteHandler = async(req, res) => {
	req.getHeader = function(this: Request, name: string): string | undefined{
		const ret = req.headers[name.toLowerCase()]
		if(typeof ret === "string"){
			return ret
		}
		if(typeof ret !== "object"){
			return undefined
		}
		return ret[0]
	}

	req.getHeaders = function(this: Request, name: string): string[]{
		const ret = this.headers[name.toLowerCase()]
		if(typeof ret === "string"){
			return [ret]
		}
		if(typeof ret !== "object"){
			return []
		}
		return ret
	}

	req.requestId = req.getHeader("cf-ray") ||
		(await errorPromise<Buffer>(cb => randomBytes(8, cb))).toString("hex")

	req.requestAddress = req.getHeader("cf-connecting-ip") ||
		req.getHeader("x-forwarded-for") ||
		req.connection.remoteAddress || ""


	res.pug = async function(this: Response, name: string, param: RenderParam){
		param.meta.url = param.meta.url || `${secrets.domain}${req.path}`
		const html = await errorPromise<string>(cb => this.render(name, param, cb))
		this.send(html)
		return html
	}

	res.redirectParams = function(this: Response, url: string, args: {[name: string]: any}){
		this.redirect(url + (url.endsWith("?") ? "" : "?") + emitUrlEncoded(args))
	}

	return true
}
