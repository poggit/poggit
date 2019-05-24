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

export function isInternalIP(ip: string){
	if(ip === "::1") return true
	const parts = ip.split(".").map(parseInt)
	return parts[0] === 10 ||
		(parts[0] === 172 && (16 <= parts[1] && parts[1] < 32)) ||
		(parts[0] === 192 && parts[1] === 168)
}
