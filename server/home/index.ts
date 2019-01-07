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

import {SessionInfo} from "../../view"
import {HomeRenderParam} from "../../view/home.view"
import {RouteHandler} from "../router"

export const homeHandler: RouteHandler = async(req, res) => {
	await res.mux({
		html: () => ({
			name: "home", param: new HomeRenderParam({
				title: "Poggit",
				description: "Poggit: PocketMine Plugin Platform", // alliteration isn't always that fun
			}, SessionInfo.create(req)),
		}),
		json: () => ({
			apiDos: "https://github.com/poggit/poggit/tree/delta/shared/api",
		}),
	})
}
