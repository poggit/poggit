export class Authentication{
	uid: number
	name: string
	token: string

	constructor(uid: number, name: string, token: string){
		this.uid = uid
		this.name = name
		this.token = token
	}
}
