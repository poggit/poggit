import * as crypto from "crypto"

export const LENGTH_AJAX = 5000
export const LENGTH_FLOW = 30 * 1000
export const LENGTH_USER_INPUT = 60 * 60 * 1000

const tokens = {} as StringMap<number>

export function generateToken(length: number, onError: ErrorHandler, consumer: (token: string) => void): void{
	crypto.randomBytes(8, (err: Error, buffer: Buffer) =>{
		if(err){
			onError(err)
		}else{
			const token = buffer.toString("hex")
			tokens[token] = new Date().getTime() + length
			consumer(token)
		}
	})
}

export function consumeToken(token: string): boolean{
	if(tokens[token] !== undefined && tokens[token] >= new Date().getTime()){
		delete tokens[token]
		return true
	}
	return false
}

export function cleanTokens(): void{
	const now = new Date().getTime()
	for(let token in tokens){
		if(tokens.hasOwnProperty(token) && tokens[token] < now){
			delete tokens[token]
		}
	}
}
