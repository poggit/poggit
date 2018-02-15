import {MysqlError} from "mysql"
import {db} from "./index"
import {SECRETS} from "../secrets"

export namespace dbUtils{
	import QueryArgument = db.types.QueryArgument

	export function createReportError(eh: ErrorHandler<MysqlError>): ErrorHandler<MysqlError>{
		const trace = SECRETS.meta.debug ? new Error().stack : ""
		return (err: MysqlError) =>{
			console.error(`Error ${err.code} executing query: ${err.message}`)
			console.error(`  at: ${err.sql}`)
			console.error("Stack trace:")
			console.trace(trace)
			eh(err)
		}
	}

	export function logQuery(query: string, args: QueryArgument[]){
		console.debug("Executing MySQL query: ", query.replace(/[\n\r\t ]+/g, " ").trim(), "|", JSON.stringify(args))
	}

	export function qm(count: number){
		return new Array(count).fill("?").join(",")
	}
}
