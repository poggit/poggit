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
import {PoggitError} from "../shared/poggitError"
import {ErrorRenderParam} from "../view/error.view"
import {secrets} from "./secrets"
import {SessionInfo} from "../view"
import {PoggitRequest} from "./ext"
import {logger} from "../shared/console"

export function errorHandler(err: any, req: Request, res: Response, next: NextFunction){
	if(!(err instanceof PoggitError)){
		err = PoggitError.internal((err as Error).message, (err as Error).stack)
	}

	const error = err as PoggitError

	if(!error.friendly){
		logger.error("Error: " + error.message)
	}

	res.status(error.status)
	if((req as PoggitRequest).outFormat === "json"){
		res.send(JSON.stringify({
			error: "Internal Server Error",
			requestId: (req as PoggitRequest).requestId,
			message: error.friendly ? error.message : undefined,
		}))
	}else{
		res.render("error", new ErrorRenderParam({
				title: "Error",
				description: error.friendly ? error.message : "An error occurred.",
				url: `${secrets.domain}${req.path}`,
			}, SessionInfo.create(req as PoggitRequest),
			`Request #${(req as PoggitRequest).requestId || "????????"}
${err.friendly ? err.details : (secrets.debug ? err : "")}`))
	}
}
