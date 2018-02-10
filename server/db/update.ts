import {MysqlError} from "mysql"
import {dbTypes} from "./types"
import {dbUtils} from "./utils"
import {pool} from "./pool"
import {secrets} from "../secrets"
import {db} from "./index"
import {dbSelect} from "./select"

export namespace dbUpdate{
	import WhereClause = dbTypes.WhereClause
	import QueryArgument = dbTypes.QueryArgument
	import TableRef = dbTypes.TableRef
	import WhereArgs = dbTypes.WhereArgs
	import logQuery = dbUtils.logQuery
	import reportError = dbUtils.reportError
	import FieldRef = dbTypes.FieldRef
	import ListWhereClause = dbSelect.ListWhereClause

	export function update(table: TableRef, set: StringMap<QueryArgument | CaseValue | null>, where: WhereClause, whereArgs: WhereArgs, onError: ErrorHandler, onUpdated: (changedRows: number) => void = () => undefined){
		const query = `UPDATE \`${table}\` SET ${Object.keys(set).map(column => `\`${column}\` = ${set[column] instanceof CaseValue ? (set[column] as CaseValue).getArgs() : "?"}`).join(",")} WHERE ${where}`
		let args: any[] = []
		const values = Object.values(set)
		for(let i = 0; i < values.length; ++i){
			if(values[i] instanceof CaseValue){
				args.push(values[i].getArgs())
			}else{
				args.push(values[i])
			}
		}
		args = args.concat(Array.isArray(whereArgs) ? whereArgs : whereArgs.getArgs())
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

	export function update_bulk<C extends StringMap<QueryArgument>>(table: TableRef, by: FieldRef, rows: StringMap<C>, where: WhereClause, whereArgs: WhereArgs, onError: ErrorHandler, onUpdated: (changedRows: number) => void = () => undefined){
		const set: StringMap<CaseValue> = {}
		for(const key in rows){
			if(!rows.hasOwnProperty(key)){
				continue
			}
			const row = rows[key]
			for(const column in row){
				if(!row.hasOwnProperty(column)){
					continue
				}
				if(set[column] === undefined){
					set[column] = new CaseValue(by)
				}
				(set[column] as CaseValue).map[key] = row[column]
			}
		}

		const list = new ListWhereClause(by, Object.keys(rows))
		where = `(${where.toString()}) AND ` + list
		whereArgs = (Array.isArray(whereArgs) ? whereArgs : whereArgs.getArgs()).concat(list.getArgs())

		update(table, set, where, whereArgs, onError, onUpdated)
	}

	export class CaseValue{
		by: FieldRef
		map: StringMap<QueryArgument>

		constructor(by: dbTypes.FieldRef, map: StringMap<dbTypes.QueryArgument> = {}){
			this.by = by
			this.map = map
		}

		getQuery(): string{
			let output = `CASE `
			for(const when in this.map){
				output += `WHEN ${this.by} = ? THEN ? `
			}
			return output
		}

		getArgs(): QueryArgument[]{
			const args = []
			for(const when in this.map){
				args.push(when)
				args.push(this.map[when])
			}
			return args
		}
	}
}
