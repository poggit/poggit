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

import * as cookieParser from "cookie-parser"
import * as express from "express"
import {Express, Request, Response} from "express"
import * as MySQLStore from "express-mysql-session"
import * as session from "express-session"
import {loadConfig} from "poggit-eps-lib-server/src/config"
import {MysqlPool} from "poggit-eps-lib-server/src/mysql"
import {ServerLogger} from "poggit-eps-lib-server/src/ServerLogger"
import {getPages} from "./pages"
import {Session} from "./session/Session"

export async function app(): Promise<Express>{
	const logger = new ServerLogger()
	const config = loadConfig()
	const mysql = new MysqlPool(logger, config)

	const app = express()
	app.enable("trust proxy")
	app.use("/assets", express.static("/main/assets"))
	app.use(cookieParser())
	app.use(session({
		name: "PgeSes",
		secret: config.cookieSecret,
		store: new MySQLStore(config.mysql) as any,
	}))

	app.use((req, res, next) => {
		let sess = req.session as Exclude<typeof req.session, undefined>
		if(!(sess.session instanceof Session)){
			sess.session = new Session(logger.prefix(req.sessionID as string), sess.session)
		}
		next()
	})

	for(const page of getPages(config, mysql)){
		page.register(app, (req: Request, res: Response) => {

		})
	}

	return app
}
