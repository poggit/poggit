import {Authentication} from "./auth/Authentication.class"
import {People} from "../consts/people"
import getAdminLevel = People.getAdminLevel
import AdminLevel = People.AdminLevel

export class Session{
	private expires: number

	auth: Authentication | null = null
	tosHidden: boolean = false
	persistLoc?: string

	constructor(duration: number){
		this.refresh(duration)
	}

	refresh(duration: number): void{
		this.expires = Date.now() + duration
	}

	expired(): boolean{
		return Date.now() > this.expires
	}

	toSessionData(): SessionData{
		return {
			session: {
				isLoggedIn: this.auth !== null,
				loginName: this.auth !== null ? this.auth.name : undefined,
				adminLevel: this.auth !== null ? People.getAdminLevel(this.auth.name) : People.AdminLevel.GUEST,
			},
			opts: {},
			tosHidden: this.auth !== null || this.tosHidden,
		}
	}

	getAdminLevel(): number{
		if(this.auth !== null){
			return getAdminLevel(this.auth.name)
		}else{
			return AdminLevel.GUEST
		}
	}
}
