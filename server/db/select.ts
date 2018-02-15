import {SECRETS} from "../secrets"
import {MysqlError, TypeCast} from "mysql"
import {dbTypes} from "./types"
import {dbUtils} from "./utils"
import {pool} from "./pool"

export namespace dbSelect{
	import FieldList = dbTypes.FieldList
	import QueryArgument = dbTypes.QueryArgument
	import TableRef = dbTypes.TableRef
	import WhereClause = dbTypes.WhereClause
	import WhereArgs = dbTypes.WhereArgs
	import FieldRef = dbTypes.FieldRef
	import IWhereClause = dbTypes.IWhereClause
	import CellValue = dbTypes.CellValue
	import ResultSet = dbTypes.ResultSet
	import logQuery = dbUtils.logQuery
	import qm = dbUtils.qm
	import createReportError = dbUtils.createReportError

	export class SelectQuery{
		fields: FieldList
		fieldArgs: QueryArgument[] = []
		from: TableRef
		joins: Join[] = []
		joinArgs: QueryArgument[] = []
		where: TypeOrArray<WhereClause>
		whereArgs: TypeOrArray<WhereArgs> = []
		group?: TableRef
		having?: WhereClause
		havingArgs: WhereArgs = []
		order?: FieldRef
		orderArgs: QueryArgument[] = []
		limit?: number

		createQuery(): string{
			let select_expr: string[] = []
			for(const key in this.fields){
				if(this.fields.hasOwnProperty(key)){
					select_expr.push(`(${this.fields[key]}) AS \`${key}\``)
				}
			}
			return `SELECT ${select_expr.join(",")} FROM \`${this.from}\` ` +
				`${this.joins.map(join => join.toString()).join(" ")} WHERE ` +
				(Array.isArray(this.where) ? this.where.map(c => c.toString()).join(" ") : this.where) +
				(this.group ? ` GROUP BY ${this.group}` : "") +
				(this.having ? ` HAVING ${this.having}` : "") +
				(this.order ? ` ORDER BY ${this.order}` : "") +
				(this.limit ? ` LIMIT ${this.limit}` : "")
		}

		createArgs(): QueryArgument[]{
			if(!(Array.isArray(this.whereArgs))){
				return this.whereArgs.getArgs()
			}
			let whereArgs: QueryArgument[] = []
			for(const arg of this.whereArgs){
				if(Array.isArray(arg)){
					whereArgs = whereArgs.concat(arg)
				}else{ // noinspection SuspiciousInstanceOfGuard
					if(typeof arg === "object" && !(arg instanceof Date) && !(arg instanceof Buffer) && arg !== null){
						whereArgs = whereArgs.concat(arg.getArgs())
					}else{
						whereArgs.push(arg)
					}
				}
			}
			return this.fieldArgs
				.concat(this.joinArgs)
				.concat(whereArgs)
				.concat(Array.isArray(this.havingArgs) ? this.havingArgs : this.havingArgs.getArgs())
				.concat(this.orderArgs)
		}

		execute<R extends StringMap<CellValue>>(onSelect: (result: ResultSet<R>) => void, onError: ErrorHandler){
			select(this.createQuery(),
				this.createArgs(),
				onSelect,
				onError)
		}
	}

	export class ListWhereClause implements IWhereClause{
		field: FieldRef
		literalList: QueryArgument[]

		constructor(field: FieldRef, literalList: QueryArgument[]){
			this.field = field
			this.literalList = literalList
		}

		toString(): string{
			return this.literalList.length !== 0 ? `(${this.field} IN (${qm(this.literalList.length)}))` : "0"
		}

		getArgs(): QueryArgument[]{
			return this.literalList
		}
	}

	export class Join{
		type: string = ""
		table: TableRef
		alias: TableRef
		on: string

		toString(): string{
			return `${this.type} JOIN \`${this.table}\` \`${this.alias}\` ON ${this.on}`
		}

		static ON(type: "INNER" | "OUTER" | "LEFT" | "RIGHT", motherTable: TableRef, motherColumn: string, satelliteTable: TableRef, satelliteColumn: string = motherColumn, motherTableAlias: string = motherTable): Join{
			return new Join(type, motherTable, `\`${motherTableAlias}\`.\`${motherColumn}\` = \`${satelliteTable}\`.\`${satelliteColumn}\``, motherTableAlias)
		}

		public constructor(type: "INNER" | "OUTER" | "LEFT" | "RIGHT", table: TableRef, on: string, alias: TableRef = table){
			this.type = type
			this.table = table
			this.alias = alias
			this.on = on
		}
	}

	export function select<R extends StringMap<CellValue>>(query: string, args: QueryArgument[], onSelect: (result: ResultSet<R>) => void, onError: ErrorHandler<MysqlError>){
		logQuery(query, args)

		onError = createReportError(onError)
		pool.query({
			sql: query,
			timeout: SECRETS.mysql.timeout,
			values: args,
			typeCast: ((field, next) =>{
				if(field.type === "BIT" && field.length === 1){
					return field.string() === "\u0001"
				}
				return next()
			}) as TypeCast,
		} as any, (err: MysqlError, results: ResultSet<R>) =>{
			if(err){
				onError(err)
			}else{
				onSelect(results)
			}
		})
	}
}
