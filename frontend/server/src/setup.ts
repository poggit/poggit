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

import * as ansi from "ansi-colors"
import {logger, setLogger} from "../../../shared/console"

setLogger({
	log: message => console.log(ansi.white(`[LOG] ${message}`)),
	error: message => console.log(ansi.redBright(`[ERROR] ${message}`)),
	warn: message => console.log(ansi.yellowBright(`[WARN] ${message}`)),
	info: message => console.log(ansi.whiteBright(`[INFO] ${message}`)),
	debug: message => console.log(ansi.white(`[DEBUG] ${message}`)),
})

export let INSTALL_DIR = "/app"
// while(!(fs.existsSync(path.join(INSTALL_DIR, "default-docker-compose.yml")))){
// 	INSTALL_DIR = path.join(INSTALL_DIR, "..")
// }

logger.debug(`Using ${INSTALL_DIR} as installation directory`)
