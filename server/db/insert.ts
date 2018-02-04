import {util} from "../util/util"
import {secrets} from "../secrets"
import {MysqlError} from "mysql"
import {dbTypes} from "./types"
import {dbUtils} from "./utils"
import {pool} from "./pool"

export namespace dbInsert{
	import TableRef = dbTypes.TableRef
	import QueryArgument = dbTypes.QueryArgument
	import logQuery = dbUtils.logQuery
	import qm = dbUtils.qm
	import reportError = dbUtils.reportError

	export function insert_dup(table: TableRef, staticFields: StringMap<QueryArgument | null>, updateFields: StringMap<QueryArgument | null>, onError: ErrorHandler, onInsert: (insertId: number) => void = () => undefined){
		const mergedFields: StringMap<QueryArgument | null> = Object.assign({}, staticFields, updateFields)
		insert(`INSERT INTO \`${table}\`
			(${Object.keys(mergedFields).map(col => "`" + col + "`").join(",")})
			VALUES (${qm(util.sizeOfObject(mergedFields))})
			ON DUPLICATE KEY UPDATE ${Object.keys(updateFields).map(col => `\`${col}\` = ?`).join(",")}`,
			Object.values(mergedFields).concat(Object.values(updateFields)), onError, onInsert)
	}

	export function insert(query: string, args: QueryArgument[], onError: ErrorHandler, onInsert: (insertId: number) => void = () => undefined){
		logQuery(query, args)
		pool.query({
			sql: query,
			timeout: secrets.mysql.timeout,
			values: args,
		} as any, (err: MysqlError, results) =>{
			if(err){
				reportError(err)
				onError(err)
			}else{
				onInsert(results.insertId)
			}
		})
	}
}
