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

import {UserObject} from "./UserObject"

export type InstallationObject = {
	id: number
	account: UserObject
	repository_selection: "selected" | "all"
	app_id: number // == secrets.github.app.id
	target_id: number // == this.account.id
	target_type: "User" | "Organization"
	permissions: {
		"repository_hooks": RW
		"single_file": RW
		"statuses": RW
		"administration": RW
		"contents": RW
		"metadata": RW
		"pull_requests": RW
	}
	events: string[]
	created_at: string
	updated_at: string
	single_file_name: string
}

export type RW = "read" | "write"
