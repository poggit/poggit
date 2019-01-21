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

export class Lazy<T>{
	init: boolean = false
	initer: () => T
	value: T | undefined

	constructor(initer: () => T){
		this.initer = initer
	}

	getValue(): T{
		if(!this.init){
			this.init = true
			this.value = this.initer()
		}
		return this.value as T
	}
}

export function lazy<T>(f: () => T){
	return new Lazy(f)
}

export class AsyncLazy<T>{
	init: boolean = false
	initer: () => Promise<T>
	value: T | undefined

	constructor(initer: () => Promise<T>){
		this.initer = initer
	}

	async get(): Promise<T>{
		if(!this.init){
			this.init = true
			this.value = await this.initer()
		}
		return this.value as T
	}
}

export function lazyAsync<T>(f: () => Promise<T>){
	return new AsyncLazy(f)
}

export type MaybeT = Exclude<Exclude<object | string | number | boolean, Function>, Promise<any>>
export type MaybeLazy<T extends MaybeT> = T | (() => T)
export type MaybePromise<T extends MaybeT> = T | Promise<T>
export type MaybeLazyPromise<T extends MaybeT> = MaybeLazy<MaybePromise<T>>

export async function resolveMaybeLazyPromise<T extends MaybeT>(t: MaybeLazyPromise<T>): Promise<T>{
	if(typeof t === "function"){
		t = (t as () => MaybePromise<T>)()
	}
	if(typeof t === "object" && t.constructor === Promise){
		t = await (t as Promise<T>)
	}
	return t
}
