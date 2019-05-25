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
import {Express, NextFunction, Request, Response} from "express"
import {loadConfig} from "poggit-eps-lib-server/src/config"
import {MysqlPool} from "poggit-eps-lib-server/src/mysql"
import {ServerLogger} from "poggit-eps-lib-server/src/ServerLogger"
import {getPages} from "./page/pages"
import {Session} from "./session/Session"
import {Exception} from "poggit-eps-lib-all/src/exception"
import {SessionStore} from "./session/SessionStore"

export async function app(): Promise<Express>{
	const logger = new ServerLogger()
	const config = loadConfig()
	const mysql = new MysqlPool(logger, config)
	const sessions = new SessionStore();

	const app = express()

	if(config.debug){
		console.warn("Debug mode is enabled. This may open security vulnerabilities.")
		app.post("/server-restart", (req, res) => {
			if(req.connection.remoteAddress === "::ffff:127.0.0.1"){
				res.send("OK")
				process.exit(42)
			}else{
				res.status(403).send(`Forbidden for ${req.connection.remoteAddress}`)
			}
		})
	}

	app.use("/assets", express.static("/main/assets"))
	app.use(cookieParser())
	if(!config.debug){
		console.info("Running in production mode. Please run behind a proxy.")
	}

	for(const page of getPages(config, mysql)){
		page.register(app)
	}

	app.use((req, res, next) => next(Exception.user(`Page not found: ${req.path}`, 404)))

	// noinspection JSUnusedLocalSymbols
	app.use((err: any, req: Request, res: Response, next: NextFunction) => {
		if(err instanceof Exception){
			res.status(err.status)
			if(err.forUser){
				res.send(`Error: ${err.message}`)
			}else{
				logger.error(err.message).catch(console.error)
				res.send("An internal error occurred.")
			}
		}
	})

	return app
}
