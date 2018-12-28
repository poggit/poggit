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
import {Connection, createConnection} from "typeorm"
import {secrets} from "../secrets"
import {MysqlConnectionOptions} from "typeorm/driver/mysql/MysqlConnectionOptions"
import {INSTALL_DIR} from "../setup"

export let db: Connection

export async function init(){
	db = await createConnection({
		type: "mysql",
		host: secrets.database.host,
		username: secrets.database.username,
		password: secrets.database.password,
		database: secrets.database.schema,
		port: secrets.database.port,
		synchronize: secrets.debug,
		logging: secrets.debug ? "all" : "info",
		entities: [path.join(INSTALL_DIR, "shared", "model", "**", "*.ts")],
	} as MysqlConnectionOptions)
}
