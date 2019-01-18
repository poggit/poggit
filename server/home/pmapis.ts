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

import {PmapisApiResult, SimpleApiVersion} from "../../shared/api/PmapisApiResult"
import {asyncMap} from "../../shared/util"
import {PmapisRenderParam} from "../../view/pmapis.view"
import {db} from "../db"
import {ApiVersion} from "../model/pm/ApiVersion"
import {RouteHandler} from "../router"

export const pmapisHandler: RouteHandler = async(req, res) => {
	const versions = await asyncMap((await db.getRepository(ApiVersion).find({
		order: {
			id: "DESC",
		},
	})), (async version => ({
		name: version.api,
		bcBreak: version.incompatible,
		minPhp: version.minimumPhp,
		download: version.downloadLink,
		description: await asyncMap(await version.description, async d => d.value),
	} as SimpleApiVersion)))

	await res.mux({
		html: () => ({
			name: "pmapis",
			param: {
				meta: {
					title: "PocketMine APIs",
					description: "A short summary for PocketMine API version history",
				},
				versions: versions,
			} as PmapisRenderParam,
		}),
		json: () => ({
			versions: versions,
		} as PmapisApiResult),
	})
}
