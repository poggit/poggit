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

import {PoggitRequest} from "../server/src/ext"
import {secrets} from "../server/src/secrets"
import {getSessionCount} from "../server/src/session/store"

export interface RenderParam{
	common: ReturnType<typeof makeCommon>
	lib: ReturnType<typeof makeLib>
	meta: MetaInfo
	session: SessionInfo | null
}

export function makeCommon(name: string){
	return {
		pageName: name,
		isDebug: secrets.debug,
		sessionCount: getSessionCount(),
		discordInvite: secrets.discord.invite,
		appSlug: secrets.github.app.slug,
	}
}

export function makeLib(req: PoggitRequest){
	return {
		csrf: () => (req.csrfToken() as string),
	}
}

export interface MetaInfo{
	title: string
	description: string
	url: string
	keywords: string[]
	image: string
}

export function makeMeta(req: PoggitRequest){
	return {
		url: `${secrets.domain}${req.path}`,
		keywords: [],
		image: "/favicon.ico",
	} as unknown as MetaInfo
}

export interface SessionInfo{
	userId: number
	username: string
}

export function makeSession(req: PoggitRequest): SessionInfo | null{
	return req.session && req.session.loggedIn ? {
		userId: req.session.userId as number,
		username: req.session.username as string,
	} : null
}
