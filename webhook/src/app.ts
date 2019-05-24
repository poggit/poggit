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

import {Express} from "express"
import * as express from "express"
import {loadConfig} from "poggit-eps-lib-server/src/config"
import {isInternalIP} from "poggit-eps-lib-server/src/internet"

export async function app(): Promise<Express>{
	const app = express()
	const config = loadConfig()
	if(config.debug){
		console.warn("Debug mode is enabled. This may open security vulnerabilities.")
		app.post("/server-restart", (req, res) => {
			if(isInternalIP(req.connection.remoteAddress || "8.8.8.8")){
				process.exit(42)
			}else{
				res.status(403).send("403 Forbidden")
			}
		})
	}

	return app
}
