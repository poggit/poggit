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

import {createServer} from "http"
import * as touch from "touch"
import {promisify} from "util"
import {app} from "./app"

app().then(async app => {
	app.set("port", 8001)
	const server = createServer(app)
	server.listen(8001)
	await new Promise((resolve, reject) => {
		server.on("error", reject)
		server.on("listening", resolve)
	})
	await promisify(touch)("/.started/wh")
	console.info("Listening on wh:8001")
}).catch(err => {
	console.error(err)
	process.exit(1)
})
