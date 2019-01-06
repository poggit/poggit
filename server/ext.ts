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

export type PoggitRequest = Request & {
	getHeader(name: string): string | undefined
	getHeaders(name: string): string[]
	requestId: string
	requestAddress: string
	session: Session
}

export type PoggitResponse = Response & {
	pug(this: Response, name: string, param: RenderParam): Promise<string>
	pug(this: Response, name: "error", param: ErrorRenderParam): Promise<string>
	redirectParams(this: Response, url: string, args: {[name: string]: any}): void
}
