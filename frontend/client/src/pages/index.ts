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

import {initPageCiDashboard} from "./ci/dashboard"
import {initPageCiProject} from "./ci/project"
import {initPageCiUser} from "./ci/user"
import {initPageError} from "./error"
import {initPageHome} from "./home"
import {initPagePmapis} from "./pmapis"
import {initPageSessionLogout} from "./session/logout"
import {initPageSubmitRules} from "./submit-rules"
import {initPageTos} from "./tos"

export function loadPage(name: string){
	switch(name){
		case "ci/dashboard":
			return initPageCiDashboard()
		case "ci/user":
			return initPageCiUser()
		case "ci/project":
			return initPageCiProject()
		case "session/logout":
			return initPageSessionLogout()
		case "error":
			return initPageError()
		case "home":
			return initPageHome()
		case "pmapis":
			return initPagePmapis()
		case "submit-rules":
			return initPageSubmitRules()
		case "tos":
			return initPageTos()
	}
	console.warn(`Could not find a page adapter for ${name}!`)
	return Promise.resolve()
}
