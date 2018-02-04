import {MysqlError} from "mysql"
import {dbTypes} from "./types"

export namespace dbUtils{
	import QueryArgument = dbTypes.QueryArgument
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
