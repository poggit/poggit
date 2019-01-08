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

import {PoggitRequest} from "../server/ext"
import {secrets} from "../server/secrets"
import {getSessionCount} from "../server/session/store"

export class RenderParam{
	common: ReturnType<typeof makeCommon>
	meta: MetaInfo
	session: SessionInfo | null

	constructor(obj: any | MetaInfo, session: SessionInfo | null){
		this.common = makeCommon()
		this.meta = Object.assign(new MetaInfo(), obj)
		this.session = session
	}
}

function makeCommon(){
	return {
		isDebug: secrets.debug,
		sessionCount: getSessionCount(),
		discordInvite: secrets.discord.invite,
		appSlug: secrets.github.app.slug,
	}
}

export class MetaInfo{
	title: string
	description: string
	url: string
	keywords: string[] = []
	image: string = "/favicon.ico"
}

export class SessionInfo{
	userId: number
	username: string

	constructor(userId: number, username: string){
		this.userId = userId
		this.username = username
	}

	static create(req: PoggitRequest): SessionInfo | null{
		return req.session && req.session.loggedIn ? new SessionInfo(
			req.session.userId as number,
			req.session.username as string,
		) : null
	}
}
