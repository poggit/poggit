import {POGGIT} from "../version"
import {join} from "path"
import * as fs from "fs"

import * as jwt from "jsonwebtoken"
import {secrets} from "../secrets"
import {gh} from "./index"

export namespace ghApp{
	const PRIVATE_KEY_PATH = join(POGGIT.INSTALL_ROOT, "secret", "app.pem")

	let jwtCache: {exp: number, value: string} | undefined

	export function getJwt(consumer: (jwt: string) => void, onError: ErrorHandler): void{
		const now = Math.floor(Date.now() / 1000)
		if(jwtCache !== undefined && now + 5 < jwtCache.exp){
			consumer(jwtCache.value)
			return
		}
		fs.readFile(PRIVATE_KEY_PATH, "utf8", (err: Error, pem: string) =>{
			jwt.sign({
				iat: now,
				exp: now + 600,
				iss: secrets.app.id,
			}, pem, {algorithm: "RS256"}, (err: Error, token: string) =>{
				if(err){
					onError(err)
				}else{
					consumer(token)
				}
			})
		})
	}

	export function asInstallation(installId: number, consumer: (token: string) => void, onError: ErrorHandler){
		getJwt((token) =>{
			gh.post(token, `installations/${installId}/access_tokens`, {}, (result: {token: string, expires_at: string}) =>{
				consumer(result.token)
			}, onError)
		}, onError)
	}
}
