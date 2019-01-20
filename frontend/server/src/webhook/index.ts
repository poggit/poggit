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
import * as crypto from "crypto"
import {HexBase64Latin1Encoding} from "crypto"
import {RequestHandler} from "express"
import {logger} from "../../../../shared/console"
import {secrets} from "../secrets"
import request = require("request-promise-native")

export const webhookPreprocessor = bodyParser.json({
	verify: (req, res, buf) => {
		const signature = req.headers["x-hub-signature"]
		if(typeof signature !== "string"){
			logger.warn("Received invalid webhook request without x-hub-signature")
			return false
		}
		const hmac = crypto.createHmac("sha1", secrets.github.webhookSecret) as {
			update(data: string | Buffer | NodeJS.TypedArray | DataView): typeof hmac;
			digest(encoding: HexBase64Latin1Encoding): string
		}
		hmac.update(buf)
		const expect = hmac.digest("hex")
		const ret = crypto.timingSafeEqual(Buffer.from(`sha1=${expect}`), Buffer.from(signature))
		if(!ret){
			logger.warn("Received invalid webhook request with invalid x-hub-signature")
		}
		return ret
	},
})

export const webhookHandler: RequestHandler = (req, res) => {
	const deliveryId = req.headers["x-github-delivery"] as string
	const event = req.headers["x-github-event"] as string

	request.post(`http://wh/${encodeURIComponent(event)}`, {
		headers: {
			"content-type": "application/json",
		},
		body: JSON.stringify({
			deliveryId: deliveryId,
			payload: req.body,
		}),
	})

	res.send("Queued")
}
