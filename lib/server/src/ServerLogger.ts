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

import {createWriteStream, mkdir, WriteStream} from "fs"
import {join} from "path"
import * as dateFormat from "dateformat"
import {BaseLogger, LogLevel} from "poggit-eps-lib-all/src/Logger"
import {HashMap} from "poggit-eps-lib-all/src/HashMap"
import {promisify} from "util"

export class ServerLogger extends BaseLogger{
	private dir: string | undefined
	private streams: HashMap<WriteStream> = {}

	async checkDate(){
		const now = new Date()
		const dir = join("/main/logs", dateFormat(now, "mmm"), dateFormat(now, "dd"))
		if(this.dir !== dir){
			this.dir = dir
			for(const key in this.streams){
				await promisify(this.streams[key].end)
			}
			this.streams = {}
			await promisify(mkdir)(dir, {recursive: true})
		}
	}

	async getStream(level: LogLevel){
		await this.checkDate()
		const name = LogLevel[level]
		if(!this.streams.hasOwnProperty(name)){
			this.streams[name] = createWriteStream(join(this.dir as string, name + ".log"))
		}
		return this.streams[name]
	}

	async log(level: LogLevel, message: string){
		const stream = await this.getStream(level)
		const now = new Date()
		await promisify(stream.write)(dateFormat(now, "[HH:MM:ss] ") + message)
	}
}
