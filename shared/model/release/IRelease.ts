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

import {AuthorType, CategoryType} from "../../consts"
import {IProject} from "../ci/IProject"
import {IUser} from "../gh/IUser"
import {IReleaseVersion} from "./IReleaseVersion"

export interface IRelease{
	id: number
	name: string
	project: Promise<IProject>
	synopsis: string
	description: string
	icon: Buffer
	licenseName: string
	licenseContent?: string

	isOfficial: boolean
	isFeatured: boolean
	callsHome: boolean
	callsThirdParty: boolean

	authors: Promise<IReleaseAuthor[]>

	category: keyof CategoryType
	minorCategories: Promise<IReleaseCategory[]>

	versions: Promise<IReleaseVersion[]>

	permissions: Promise<IReleasePermission[]>
	commands: Promise<IReleaseCommand[]>
}

export interface IReleaseAuthor{
	id: number
	author: Promise<IUser>
	release: Promise<IRelease>
	type: keyof AuthorType
}

export interface IReleaseCategory{
	id: number
	release: Promise<IRelease>
	category: keyof CategoryType
}

export interface IReleasePermission{
	id: number
	release: Promise<IRelease>
	name: string
	description: string
}

export interface IReleaseCommand{
	id: number
	release: Promise<IRelease>
	name: string
	description: string
}
