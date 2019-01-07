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

import {Resource} from "../../../server/model/resource/Resource"
import {BuildType} from "../../consts"
import {IUser} from "../gh/IUser"
import {IProject} from "./IProject"

export interface IBuild{
	id: number
	project: IProject
	cause: keyof BuildType
	number: number

	created: Date
	resource: Resource

	branch: string
	sha: string
	triggerUser: IUser

	prHeadRepo: number
	prNumber: number

	path: string
	log: string
}
