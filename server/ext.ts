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
import {ErrorRenderParam} from "../view/error.view"
import {RenderParam} from "../view"
import {Session} from "./session/Session"
import {ApiResult} from "../shared/api/ApiResult"

export type PoggitRequest = Request & {
	getHeader(this: PoggitRequest, name: string): string | undefined
	getHeaders(this: PoggitRequest, name: string): string[]
	requestId: string
	requestAddress: string
	outFormat: "html" | "json"
	session: Session
}

export type PoggitResponse = Response & {
	mux(this: PoggitResponse, formats: {
		html?: () => {name: string, param: RenderParam}
		json?: () => ApiResult
	}): Promise<void>
	pug(this: PoggitResponse, name: string, param: RenderParam): Promise<string>
	pug(this: PoggitResponse, name: "error", param: ErrorRenderParam): Promise<string>
	redirectParams(this: PoggitResponse, url: string, args: {[name: string]: any}): void
}
