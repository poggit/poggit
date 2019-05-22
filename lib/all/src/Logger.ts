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

export interface Logger{
	error(message: string): void

	warn(message: string): void

	info(message: string): void

	debug(message: string): void

	verbose(message: string): void

	wrap(wrapper: (message: string) => Promise<string>): Logger

	prefix(prefix: string): Logger
}

export enum LogLevel{ error, warn, info, debug, verbose}

export abstract class BaseLogger implements Logger{
	async error(message: string): Promise<void>{
		await this.log(LogLevel.error, message)
	}

	async warn(message: string): Promise<void>{
		await this.log(LogLevel.warn, message)
	}

	async info(message: string): Promise<void>{
		await this.log(LogLevel.info, message)
	}

	async debug(message: string): Promise<void>{
		await this.log(LogLevel.debug, message)
	}

	async verbose(message: string): Promise<void>{
		await this.log(LogLevel.verbose, message)
	}

	abstract log(level: LogLevel, message: string): Promise<void>

	wrap(wrapper: (message: string) => Promise<string>){
		return new LoggerWrapper(this, wrapper)
	}

	prefix(prefix: string){
		return this.wrap(message => Promise.resolve(`[${prefix}] ${message}`))
	}
}

export class LoggerWrapper extends BaseLogger{
	private readonly parent: BaseLogger
	private readonly wrapper: (message: string) => Promise<string>

	constructor(parent: BaseLogger, wrapper: (message: string) => Promise<string>){
		super()
		this.parent = parent
		this.wrapper = wrapper
	}

	async log(level: LogLevel, message: string): Promise<void>{
		await this.parent.log(level, await this.wrapper(message))
	}
}
