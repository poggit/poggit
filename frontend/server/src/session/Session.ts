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

import * as OctoKit from "@octokit/rest"
import {logger} from "../../../../shared/console"
import {Guard} from "../../../../shared/util/Guard"

export class Session{
	loggedIn: boolean = false
	loggingIn = new Guard()
	userId?: number
	username?: string
	private token?: string

	gh = new OctoKit({
		headers: {
			accept: "application/vnd.github.v3+json",
		},
	})

	loginTarget: string = "/"

	firstOnline: Date = new Date()
	lastOnline: Date = new Date()

	async login(token: string){
		if(this.loggedIn){
			return
		}

		await this.loggingIn.execute(async() => {
			this.gh.authenticate({
				type: "token",
				token: token,
			})
			const user = await this.gh.users.getAuthenticated({})
			logger.info(`${user.data.login} logged in`)
			this.userId = user.data.id
			this.username = user.data.login
			this.token = token
			this.loggedIn = true
		})

		// TODO database stuff
	}

	async logout(){
		await this.loggingIn.execute(async() => {
			this.loggedIn = false
			this.userId = this.username = this.token = undefined
		})
	}
}
