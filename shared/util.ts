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

import * as fs from "fs"

export async function errorPromise<R, T = Error>(fn: (cb: (err: T, ret?: R) => void) => void): Promise<R>{
	return new Promise((resolve, reject) => {
		fn((err: any, ret?: R) => {
			if(err){
				reject(err)
			}else{
				resolve(ret)
			}
		})
	})
}

export function isFile(fileName: string): Promise<boolean>{
	return new Promise((resolve, reject) => {
		fs.stat(fileName, (err, stats) => {
			if(err){
				reject(err)
			}else{
				resolve(stats.isFile())
			}
		})
	})
}

export function getEnumNames<T extends {[i: number]: string}>(e: T, startIndex: number = 0): (keyof T)[]{
	const ret = [] as (keyof T)[]
	for(let i = startIndex; typeof e[i] === "string"; i++){
		ret.push(e[i] as keyof T)
	}
	return ret
}
