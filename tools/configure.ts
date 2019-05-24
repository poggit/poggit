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

import {readFile, writeFile} from "fs"
import * as path from "path"
import * as read from "read"
import {Options} from "read"
import {promisify} from "util"
import {safeDump, safeLoad} from "js-yaml"

function promisifyRead(options: Options): Promise<string>{
	return new Promise((resolve, reject) => read(options, (err, result) => err ? reject(err) : resolve(result)))
}

function requireBoolean(value: string): [boolean, boolean]{
	if(["y", "yes", "t", "true", "1"].includes(value.toLowerCase())){
		return [true, true]
	}
	if(["n", "no", "f", "false", "0"].includes(value.toLowerCase())){
		return [true, false]
	}
	console.warn("Invalid input, expected y/n")
	return [false, false]
}

function ask(prompt: string, silent: boolean): Promise<string>
function ask<R>(prompt: string, silent: boolean, validator: (value: string) => [boolean, R]): Promise<R>
async function ask<R>(
	prompt: string, silent: boolean,
	validator?: (value: string) => [boolean, R]): Promise<R>{
	validator = validator || (value => [true, value as unknown as R])

	const envName = prompt.replace(/ /g, "_").toUpperCase()
	if(process.env[envName] !== undefined){
		const value = process.env[envName] as string
		const [ok, ret] = validator(value)
		if(ok){
			return ret
		}
	}

	if(process.env.DEBIAN_FRONTEND === "noninteraactive"){
		throw `Missing environment variable ${envName}`
	}

	while(true){
		const [ok, ret] = validator(await promisifyRead({prompt: prompt + ":", silent}))
		if(ok){
			return ret
		}
	}
}

async function run(){
	const docker = safeLoad(await promisify(readFile)(path.join(__dirname, "../default-docker-compose.yml"), "utf8"))
	docker.services.db.environment.MYSQL_ROOT_PASSWORD = await ask("MySQL root password", true)
	docker.services.db.environment.MYSQL_USER = await ask("MySQL user", false)
	docker.services.db.environment.MYSQL_PASSWORD = await ask("MySQL password", true)
	await promisify(writeFile)(path.join(__dirname, "../docker-compose.yml"), safeDump(docker))

	await promisify(writeFile)(path.join(__dirname, "../config.js"), "module.exports = " + JSON.stringify({
		debug: await ask("Debug mode", false, requireBoolean),
		mysql: {
			host: "db",
			port: 3306,
			user: docker.services.db.environment.MYSQL_USER,
			password: docker.services.db.environment.MYSQL_PASSWORD,
			database: "poggit",
		},
		cookieSecret: await ask("Cookie secret", true),
	}))
}

run().catch(console.error)
