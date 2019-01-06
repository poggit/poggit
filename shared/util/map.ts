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

export namespace map{
	export type Mapping<T> = {[name: string]: T}

	export function count(object: Mapping<any>){
		let cnt = 0
		for(const name in object){
			if(object.hasOwnProperty(name)){
				cnt++
			}
		}
		return cnt
	}

	export function toPairs<T>(object: Mapping<T>): [string, T][]{
		const pairs = [] as [string, T][]
		for(const name in object){
			if(object.hasOwnProperty(name)){
				pairs.push([name, object[name]])
			}
		}
		return pairs
	}

	export function fromPairs<T>(pairs: [string, T][]): map.Mapping<T>{
		const object = {} as Mapping<T>
		for(const [name, value] of pairs){
			object[name] = value
		}
		return object
	}
}
