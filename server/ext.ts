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

import {Request, Response} from "express"
import {ApiResult} from "../shared/api/ApiResult"
import {RenderParam} from "../view"
import {Session} from "./session/Session"

export type PoggitRequest = Request & {
	getHeader(this: PoggitRequest, name: string): string | undefined
	getHeaders(this: PoggitRequest, name: string): string[]
	requestId: string
	requestAddress: string
	outFormat: "html" | "json"
	sessionId: string
	session: Session
	loggedInAs: number | null
}

export interface HtmlParam{
	name: string
	param: RenderParam
}

export type PoggitResponse = Response & {
	mux(this: PoggitResponse, formats: {
		html?: () => (HtmlParam | Promise<HtmlParam>)
		json?: () => (ApiResult | Promise<ApiResult>)
	}): Promise<void>
	pug(this: PoggitResponse, name: string, param: RenderParam): Promise<string>
	redirectParams(this: PoggitResponse, url: string, args: {[name: string]: any}): void
}
