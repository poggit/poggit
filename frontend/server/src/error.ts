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

import {NextFunction, Request, Response} from "express"
import {logger} from "../../../shared/console"
import {PoggitError} from "../../../shared/PoggitError"
import {makeCommon, makeLib, makeSession} from "../../view"
import {ErrorRenderParam} from "../../view/error.view"
import {PoggitRequest} from "./ext"
import {secrets} from "./secrets"

export function errorHandler(err: any, req: Request, res: Response, next: NextFunction){
	if(!(err instanceof PoggitError)){
		err = PoggitError.internal((err as Error).message, (err as Error).stack)
	}

	const error = err as PoggitError

	if(!error.friendly){
		logger.error("Error: " + error.message)
		if(error.details){
			logger.debug(error.details)
		}
	}

	res.status(error.status)
	if((req as PoggitRequest).outFormat === "json"){
		res.send(JSON.stringify({
			error: error.friendly ? error.apiCode : "InternalServerError",
			requestId: (req as PoggitRequest).requestId,
			message: error.friendly ? error.message : undefined,
		}))
	}else{
		res.render("error", {
			meta: {
				title: "Error",
				description: error.friendly ? error.message :
					`An error occurred. ${secrets.debug ? `(${error.message})` : ""}`,
				url: `${secrets.domain}${req.path}`,
				keywords: [] as string[],
				image: "/favicon.ico",
			},
			common: makeCommon("error"),
			session: (req as any).session ? makeSession(req as any) : null,
			lib: makeLib(req as any),
			details: `Request #${(req as PoggitRequest).requestId || "????????"}
${(error.friendly || secrets.debug ? error.details : "") || ""}`,
		} as ErrorRenderParam)
	}
}
