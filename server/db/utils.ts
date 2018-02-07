import {MysqlError} from "mysql"
import {db} from "./index"

export namespace dbUtils{
	import QueryArgument = db.types.QueryArgument
	export const reportError: ErrorHandler = (err: MysqlError) =>{
		console.error(`Error ${err.code} executing query: ${err.message}`)
		console.error(`Error at: '${err.sql}'`)
	}

	export function logQuery(query: string, args: QueryArgument[]){
		console.debug("Executing MySQL query: ", query.replace(/[\n\r\t ]+/g, " ").trim(), "|", JSON.stringify(args))
	}

	export function qm(count: number){
		return new Array(count).fill("?").join(",")
	}
}
