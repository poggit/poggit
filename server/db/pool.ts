import {SECRETS} from "../secrets"
import * as mysql from "mysql"

export const pool = mysql.createPool({
	connectionLimit: SECRETS.mysql.poolSize,
	host: SECRETS.mysql.host,
	user: SECRETS.mysql.user,
	password: SECRETS.mysql.password,
	database: SECRETS.mysql.schema,
	port: SECRETS.mysql.port,
})
