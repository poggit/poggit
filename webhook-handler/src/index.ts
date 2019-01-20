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

import * as bodyParser from "body-parser"
import * as express from "express"
import {RequestHandler} from "express"
import * as process from "process"
import {log} from "util"
import {logger} from "../../shared/console"
import {installationHandler} from "./handlers/installation"
import {pushHandler} from "./handlers/push"
import {LogWriter} from "./LogWriter"
import {secrets} from "./secrets"
import {WebhookHandler} from "./WebhookHandler"

export const app = express()

export async function ready(){
	app.use(bodyParser.json())

	app.post("/push", wrap(pushHandler))
	app.post("/installation", wrap(installationHandler))
	app.post("/integration_installation", ignoreEvent)

	if(secrets.debug){
		app.get("/restart", (req, res) => {
			const addr = req.connection.remoteAddress
			if(addr === "::ffff:127.0.0.1"){
				res.end()
				process.exit(42)
			}else{
				res.status(403).send("Only accessible from localhost")
			}
		})
	}

	app.use((req, res) => {
		logger.warn(`Received unhandled webhook event: ${req.path}`)
		res.end()
	})

	return app
}

function wrap(handler: WebhookHandler<any>): RequestHandler{
	return (req, res) => {
		const {deliveryId, payload} = req.body as {deliveryId: string, payload: any}
		logger.info(`Handling ${req.path} webhook delivery ${deliveryId}`)
		res.end()
		handler(payload, new LogWriter(deliveryId))
			.catch(err => {
				logger.error(`Error handling webhook delivery ${deliveryId}: ${err}`) // TODO more notifications
			})
	}
}

const ignoreEvent: RequestHandler = (req, res) => {
	res.end()
}
