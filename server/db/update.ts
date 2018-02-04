import {MysqlError} from "mysql"
import {dbTypes} from "./types"
import {dbUtils} from "./utils"
import {pool} from "./pool"
import {secrets} from "../secrets"

export namespace dbUpdate{
	import WhereClause = dbTypes.WhereClause
	import QueryArgument = dbTypes.QueryArgument
	import TableRef = dbTypes.TableRef
	import WhereArgs = dbTypes.WhereArgs
	import logQuery = dbUtils.logQuery
	import reportError = dbUtils.reportError

	export function update(table: TableRef, set: StringMap<QueryArgument | null>, where: WhereClause, whereArgs: WhereArgs, onError: ErrorHandler, onUpdated: (changedRows: number) => void = () => undefined){
		const query = `UPDATE \`${table}\`
				SET ${Object.keys(set).map(column => `\`${column}\` = ?`).join(",")}
				WHERE ${where}`
		const args = Object.values(set).concat(Array.isArray(whereArgs) ? whereArgs : whereArgs.getArgs())
		logQuery(query, args)
		pool.query({
			sql: query,
			values: args,
			timeout: secrets.mysql.timeout,
		} as any, (err: MysqlError, results) =>{
			if(err){
				reportError(err)
				onError(err)
			}else{
				onUpdated(results.changedRows)
			}
		})
	}

}
