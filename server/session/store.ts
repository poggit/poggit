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

import {Session} from "./Session"
import {SESSION_TIMEOUT} from "./index"
import {map} from "../../shared/util/map"
import Mapping = map.Mapping

const store = {} as Mapping<Session>
let sessionCount: number

export async function getSession(cookie: string): Promise<Session | undefined>{
	const ret = store[cookie]
	if(ret !== undefined){
		if(Date.now() - ret.lastOnline.getTime() >= SESSION_TIMEOUT){
			delete store[cookie]
			return undefined
		}
		ret.lastOnline = new Date()
		return ret
	}
	return undefined
}

export async function createSession(cookie: string): Promise<Session>{
	await cleanStore()
	sessionCount++
	return store[cookie] = new Session()
}

export async function cleanStore(){
	for(const name in store){
		if(!store.hasOwnProperty(name)){
			continue
		}

		if(Date.now() - store[name].lastOnline.getTime() >= SESSION_TIMEOUT){
			delete store[name]
			sessionCount--
		}
	}
}

export function getSessionCount(){
	return sessionCount
}
