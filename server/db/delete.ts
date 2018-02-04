import {MysqlError} from "mysql"
import {dbUtils} from "./utils"
import {secrets} from "../secrets"
import {pool} from "./pool"
import {dbTypes} from "./types"

export namespace dbDelete{
	import reportError = dbUtils.reportError
	import logQuery = dbUtils.logQuery
	import WhereArgs = dbTypes.WhereArgs
	import WhereClause = dbTypes.WhereClause
	import TableRef = dbTypes.TableRef

	export function del(table: TableRef, where: WhereClause, whereArgs: WhereArgs, onError: ErrorHandler){
		const query = `DELETE FROM \`${table}\` WHERE ${where}`
		logQuery(query, Array.isArray(whereArgs) ? whereArgs : whereArgs.getArgs())
		pool.query({
			sql: query,
			values: Array.isArray(whereArgs) ? whereArgs : whereArgs.getArgs(),
			timeout: secrets.mysql.timeout,
		} as any, (err: MysqlError) =>{
			if(err){
				reportError(err)
				onError(err)
			}
		})
	}
}
