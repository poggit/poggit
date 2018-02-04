declare interface SessionData{
	session: {
		isLoggedIn: boolean
		adminLevel: number
		loginName?: string
	}
	opts: {}
	tosHidden: boolean
}

declare const sessionData: SessionData
