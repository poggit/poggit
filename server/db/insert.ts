import {util} from "../util"
import {SECRETS} from "../secrets"
import {MysqlError} from "mysql"
import {dbTypes} from "./types"
import {dbUtils} from "./utils"
import {pool} from "./pool"

export namespace dbInsert{
	import TableRef = dbTypes.TableRef
	import QueryArgument = dbTypes.QueryArgument
	import logQuery = dbUtils.logQuery
	import qm = dbUtils.qm
	import createReportError = dbUtils.createReportError

	export function insert_dup(table: TableRef, rows: InsertRow[], onError: ErrorHandler, onInsert: (insertId: number) => void = () => undefined){
		if(rows.length === 0){
			onInsert(0)
			return
		}
		const columns = Object.keys(rows[0].mergedFields)
		insert(`INSERT INTO \`${table}\`
			(${columns.map(col => `\`${col}\``).join(",")})
			VALUES ${rows.map((row) => row.getQuery()).join(",")}
			ON DUPLICATE KEY UPDATE ${columns.map(col => `\`${col}\` = VALUES(\`${col}\`)`).join(",")}`,
			util.flattenArray(rows.map((row) => row.getArgs())), onError, onInsert)
	}

	export class InsertRow{
		staticFields: StringMap<QueryArgument | null>
		updateFields: StringMap<QueryArgument | null>
		mergedFields: StringMap<QueryArgument | null>

		constructor(staticFields: StringMap<dbTypes.QueryArgument | null>, updateFields: StringMap<dbTypes.QueryArgument | null>){
			this.staticFields = staticFields
			this.updateFields = updateFields
			this.mergedFields = Object.assign({}, staticFields, updateFields)
		}

		getQuery(): string{
			return `(${qm(util.sizeOfObject(this.mergedFields))})`
		}

		getArgs(): QueryArgument[]{
			return Object.values(this.mergedFields)
		}
	}

	export function insert(query: string, args: QueryArgument[], onError: ErrorHandler<MysqlError>, onInsert: (insertId: number) => void = () => undefined){
		logQuery(query, args)

		onError = createReportError(onError)
		pool.query({
			sql: query,
			timeout: SECRETS.mysql.timeout,
			values: args,
		} as any, (err: MysqlError, results) =>{
			if(err){
				onError(err)
			}else{
				onInsert(results.insertId)
			}
		})
	}
}
