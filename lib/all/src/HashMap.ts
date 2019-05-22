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

export type HashMap<V> = {[key: string]: V}

export function inPlaceTransform<V, U>(map: HashMap<V>, transform: (v: V) => U): HashMap<U>{
	for(const key in map){
		const value = map[key]
		const newValue = transform(value)
		map[key] = newValue as any
	}
	return map as unknown as HashMap<U>
}

export function biMap<V, U>(map: HashMap<V>, transform: (k: string, v: V) => U) : U[]{
	const array = [] as U[]
	for(const key in map){
		array.push(transform(key, map[key]))
	}
	return array
}
