import {MysqlError} from "mysql"
import {dbUtils} from "./utils"
import {SECRETS} from "../secrets"
import {pool} from "./pool"
import {dbTypes} from "./types"

export namespace dbDelete{
	import logQuery = dbUtils.logQuery
	import WhereArgs = dbTypes.WhereArgs
	import WhereClause = dbTypes.WhereClause
	import TableRef = dbTypes.TableRef
	import createReportError = dbUtils.createReportError

	export function del(table: TableRef, where: WhereClause, whereArgs: WhereArgs, onError: ErrorHandler<MysqlError>){
		const query = `DELETE FROM \`${table}\` WHERE ${where}`

		logQuery(query, Array.isArray(whereArgs) ? whereArgs : whereArgs.getArgs())

		onError = createReportError(onError)
		pool.query({
			sql: query,
			values: Array.isArray(whereArgs) ? whereArgs : whereArgs.getArgs(),
			timeout: SECRETS.mysql.timeout,
		} as any, (err: MysqlError) =>{
			if(err){
				onError(err)
			}
		})
	}
}
