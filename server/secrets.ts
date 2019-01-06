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

import * as path from "path"
import {INSTALL_DIR} from "./setup"

export const secrets: {
	debug: boolean
	database: {
		host: string
		username: string
		password: string
		schema: string
		port: number
	}
	domain: string // including protocol:// but without a trailing slash
	github: {
		app: {
			id: number
			slug: string
			privateKey: string
		}
		oauth: {
			clientId: string
			clientSecret: string
		}
		webhookSecret: string
		publicToken: string
	}
} = require(path.join(INSTALL_DIR, "secrets", "secrets.js"))
