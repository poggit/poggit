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

import {ErrorApiResult} from "../../shared/api/ErrorApiResult"
import {ErrorRenderParam} from "../../view/error.view"
import {RouteHandler} from "./index"

export const notFoundHandler: RouteHandler = async(req, res) => {
	res.status(404)
	await res.mux({
			html: () => ({
				name: "error",
				param: {
					meta: {
						title: "404 Not Found",
						description: `Page ${req.path} not found`,
					},
					details: `Redirected from: ${req.getHeader("referer")}`,
				} as ErrorRenderParam,
			}),
			json: () => ({
				"error": "NotSuchEndpoint",
			} as ErrorApiResult),
		},
	)
}
