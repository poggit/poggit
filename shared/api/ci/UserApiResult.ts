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

/**
 * In Poggit terminology, a "user" refers to one of these:
 * - A GitHub User account ("UserAccount")
 * - A GitHub Organization account
 *
 * The user has a data entry in Poggit if at least one of these is true:
 * - the user registered on Poggit (Beta)
 * - the user registered on Poggit (Delta)
 * - the user has triggered a Poggit build (possibly not registered on Poggit if the user is only a collaborator or a Pull Request contributor)
 * - the user owns a Poggit-enabled repository (possibly not registered on Poggit if the user is an organization or if a Poggit-enabled repository is transferred to the user)
 */
export interface UserApiResult{
	/** The GitHub user ID */
	id: number
	/** The GitHub user login */
	name: string
	/** Whether the user is an organization */
	isOrg: boolean
	/**
	 * Lists the projects directly owned by the user.
	 * Only available if the `projects` GET parameter is given.
	 * Pass the additional `projectsLevel` GET parameter:
	 * * `projectsLevel=owner`: only list the projects in repos directly under the account `@:name`
	 * * `projectsLevel=admin`: only list the projects in repos that the user has admin access to
	 * * `projectsLevel=write`: only list the projects in repos that the user has write access to
	 */
	projects?: UserApiResult_Project[]
	/**
	 * Lists the released plugins maintained by the user.
	 * Only available if the `releases` GET parameter is given.
	 * Pass the additional `releasesLevel` GET parameter:
	 * * `releasesLevel=owner`: only list the releases from projects in repos directly under the account `@:name`
	 * * `releasesLevel=editor`: only list the releases that the user has permission to edit
	 * * `releasesLevel=author`: only list the releases where the user is in the author list. In addition:
	 *   * `releasesLevel=author.:authorLevel`: only list the releases where the user is at least a `:authorLevel`-level author. Possible values of `:authorLevel` include:
	 *     * `owner` (semantically identical to `releasesLevel=owner`)
	 *     * `collaborator`
	 *     * `contributor`
	 *     * `translator`
	 *     * `requester`
	 */
	releases?: {}[]
}

export interface UserApiResult_Project{
	id: number
	name: string
	repo: {
		id: number
		name: string
	}
	released: boolean
	releaseId: number | null
}
