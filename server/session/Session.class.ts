import {secrets} from "../secrets"
import {people} from "../consts/people"
import {Authentication} from "./auth/Authentication.class"

export class Session{
	private expires: number

	auth: Authentication | null = null
	tosHidden: boolean = false
	persistLoc?: string

	constructor(duration: number){
		this.refresh(duration)
	}

	refresh(duration: number): void{
		this.expires = new Date().getTime() + duration
	}

	expired(): boolean{
		return new Date().getTime() > this.expires
	}

	toSessionData(): SessionData{
		return {
			session: {
				isLoggedIn: this.auth !== null,
				loginName: this.auth !== null ? this.auth.name : undefined,
				adminLevel: this.auth !== null ? people.getAdminLevel(this.auth.name) : people.AdminLevel.GUEST,
			},
			opts: {},
			tosHidden: this.auth !== null || this.tosHidden,
		}
	}
}
