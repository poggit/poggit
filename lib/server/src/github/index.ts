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

import {GitHub} from "poggit-eps-lib-all/src/github-types"
import {HashMap} from "poggit-eps-lib-all/src/HashMap"
import * as request from "request"
import {Response} from "request"

export class GitHubClient{
	private readonly token: TokenProvider

	constructor(token: TokenProvider){
		this.token = token
	}

	private static takeHttpArgs(url: string, args: HashMap<string | number>): string{
		for(const name in args){
			if(url.includes(`:${name}`)){
				url = url.replace(`:${name}`, args[name].toString())
				delete args[name]
			}
		}
		return `https://api.github.com/${url}`
	}

	private async send(url: string, method: string, args: HashMap<string | number>){
		url = GitHubClient.takeHttpArgs(url, args)
		const token = await this.token.provideToken()
		return await new Promise<Response>((resolve, reject) => {
			request(url, {
				method,
				auth: {bearer: token},
				body: method === "GET" ? "" : JSON.stringify(args),
				headers: {
					accept: "application/vnd.github.v3+json",
					authorization: `bearer ${token}`,
					"user-agent": "Poggit/Epsilon npm/request",
				},
			}, ((error, response) => {
				if(error){
					reject(error)
				}else if(response.statusCode >= 400){
					reject({
						statusCode: response.statusCode,
						body: response.body,
					})
				}else{
					resolve(response)
				}
			}))
		})
	}

	private async sendGet(url: string, args: HashMap<string | number>){
		return await this.send(url, "GET", args)
	}

	private async sendGetJson(url: string, args: HashMap<string | number>){
		const response = await this.send(url, "GET", args)
		return JSON.parse(response.body)
	}

	async getUserById(id: number): Promise<GitHub.FullUser>{
		return await this.sendGetJson("user/:id", {id})
	}
}

export interface TokenProvider{
	provideToken(): Promise<string>
}
