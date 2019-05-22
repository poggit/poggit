/**
 * Copyright 2019 Poggit
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

import {Exception} from "poggit-eps-lib-all/src/exception"
import {Logger} from "poggit-eps-lib-all/src/Logger"
import {GitHubClient} from "../github"
import {CURRENT_TIMESTAMP, MysqlPool} from "../mysql"
import {Where} from "../mysql/where"

export class UserManager{
	private logger: Logger
	private mysql: MysqlPool
	private commonClient: GitHubClient

	constructor(logger: Logger, mysql: MysqlPool, commonClient: GitHubClient){
		this.logger = logger.prefix("UserManager")
		this.mysql = mysql
		this.commonClient = commonClient
	}

	async login(userId: number, username: string, email: string | null){
		const record = await this.mysql.select("user").columns(["name", "is_org", "registered"])
			.where(Where.EQ("id", userId))
			.fetchSingle()
		if(record === null){
			await this.registerUser(userId, username, email)
		}else{
			if(record.name !== username){
				await this.validateVacancy(username)
				this.logger.warn(`Detected account #${userId} rename: ${record.name} -> ${username}`)
				await this.mysql.update("user", {name: username}, Where.EQ("id", userId))
			}
			if(record.is_org){
				this.logger.warn(`Detected account ${username} #${userId} org2user`)
				await this.mysql.update("user", {is_org: false}, Where.EQ("id", userId))
			}
		}
	}

	async registerUser(id: number, name: string, email: string | null){
		await this.validateVacancy(name)
		await this.mysql.insert("user", {
			id,
			name,
			email,
			is_org: false,
			first_login: CURRENT_TIMESTAMP,
			last_login: CURRENT_TIMESTAMP,
			registered: true,
			access_level: "user",
		})
		this.logger.info(`Registered user ${name}#${id}`)
	}

	async validateVacancy(username: string){
		const current = await this.mysql.select("user").columns(["id"]).where(Where.EQ("name", username)).fetchSingle()
		if(current !== null){
			const data = await this.commonClient.getUserById(current.id)
			if(data.login === username){
				throw Exception.internal(`Could not release vacancy of username "${username}"`)
			}
			await this.mysql.update("user", {name: data.login}, Where.EQ("id", current.id))
		}
	}

	async touchIp(id: number, ip: string){
		await Promise.all([
			this.mysql.update("user", {
				last_login: CURRENT_TIMESTAMP,
			}, Where.EQ("id", id)),
			this.mysql.insert("user_ip", {
				user: id,
				ip,
				first: CURRENT_TIMESTAMP,
				last: CURRENT_TIMESTAMP,
			}, true, ["user", "ip", "first"]),
		])
	}
}
