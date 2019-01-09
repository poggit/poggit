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
import {Response} from "express"
import {emitUrlEncoded, errorPromise} from "../../shared/util"
import {makeCommon, makeMeta, makeSession, RenderParam} from "../../view"
import {ErrorRenderParam} from "../../view/error.view"
import {HtmlParam, PoggitRequest, PoggitResponse} from "../ext"
import {RouteHandler} from "../router"

export const utilMiddleware: RouteHandler = async(req, res) => {
	req.getHeader = function(this: PoggitRequest, name: string): string | undefined{
		const ret = req.headers[name.toLowerCase()]
		if(typeof ret === "string"){
			return ret
		}
		if(typeof ret !== "object"){
			return undefined
		}
		return ret[0]
	}

	req.getHeaders = function(this: PoggitRequest, name: string): string[]{
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
	res.header("x-poggit-request-id", req.requestId)

	req.requestAddress = req.getHeader("cf-connecting-ip") ||
		req.getHeader("x-forwarded-for") ||
		req.connection.remoteAddress || ""

	req.outFormat = (req.accepts("html", "json") as "html" | "json" | undefined) || "html"
	if(req.query.format === "json"){
		req.outFormat = "json"
	}

	res.pug = async function(this: Response, name: string, param: RenderParam){
		param.meta = Object.assign(makeMeta(req), param.meta || {})
		param.common = makeCommon(name)
		param.session = makeSession(req)
		const html = await errorPromise<string>(cb => this.render(name, param, cb))
		this.send(html)
		return html
	}

	res.mux = async function(this: PoggitResponse, formats){
		switch(req.outFormat){
			case "html":
				if(formats.html){
					const html = formats.html()
					const {name, param} = html.constructor === Promise ? await html : (html as HtmlParam)
					await this.pug(name, param)
				}else{
					this.status(406)
					await this.pug("error", {
						meta: {
							title: "406 Not Acceptable",
							description: "Webpage response is not supported",
						},
					} as ErrorRenderParam)
				}
				return
			case "json":
				if(formats.json){
					const type = formats.json()
					this.send(JSON.stringify(type.constructor === Promise ? await type : type))
				}else{
					this.status(406)
					this.send(JSON.stringify({error: "406 Not Acceptable: JSON response is not supported"}))
				}
				return
			default:
				throw new Error("Unexpected control flow")
		}
	}

	res.redirectParams = function(this: Response, url: string, args: {[name: string]: any}){
		this.redirect(url + (url.endsWith("?") ? "" : "?") + emitUrlEncoded(args))
	}

	return true
}
