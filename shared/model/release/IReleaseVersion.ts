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

import {IBuild} from "../ci/IBuild"
import {IApiVersion} from "../pm/IApiVersion"
import {IResource} from "../resource/IResource"
import {IRelease} from "./IRelease"
import {IReleaseReview} from "./IReleaseReview"

export interface IReleaseVersion{
	id: number
	release: IRelease
	version?: string
	artifact: IResource
	date: Date

	build: IBuild
	changelog: string

	isExperimental: boolean
	requiresMysql: boolean
	requiresConfig: boolean
	requiresOthers: string

	isLatestApi: boolean
	isLatestVersion: boolean

	apiVersions: IApiVersion[]

	depExtensions: IReleaseDepExt[]
	depPlugins: IReleaseDepPlugin[]

	reviews: IReleaseReview[]
}


export interface IReleaseDepExt{
	id: number
	release: IReleaseVersion
	extension: string
}

export interface IReleaseDepPlugin{
	id: number
	dependent: IReleaseVersion
	dependency: IReleaseVersion
	dependencyMax?: IReleaseVersion
	optional: boolean
}
