import {secrets} from "../secrets"
import * as mysql from "mysql"

export const pool = mysql.createPool({
	connectionLimit: secrets.mysql.poolSize,
	host: secrets.mysql.host,
	user: secrets.mysql.user,
	password: secrets.mysql.password,
	database: secrets.mysql.schema,
	port: secrets.mysql.port,
})
