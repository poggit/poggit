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

import {db} from "./index"
import {User} from "../../shared/model/gh/User"
import {publicClient} from "../util/gh"

export const UserDb = {
	get: async(id: number) => {
		const repo = db.getRepository(User)
		let user = await repo.findOne(id)
		if(user === undefined){
			user = new User()
			user.id = id
			await publicClient.users.getByUsername
			await repo.insert(user)
		}
	},
}
