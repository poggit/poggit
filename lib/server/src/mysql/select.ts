/**
 * Copyright 2019 Poggit
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

import {biMap, HashMap, inPlaceTransform} from "poggit-eps-lib-all/src/HashMap"
import {MysqlPool, QueryExpr} from "./index"
import {Where, whereClauseToQueryExpr} from "./where"

export class SelectQuery<T = any>{
	private readonly mysql: MysqlPool
	private readonly _table: string
	private _columns: HashMap<QueryExpr> = {}
	private _where?: Where.Clause = undefined
	private _joins: QueryExpr[] = []
	private _group: {query: QueryExpr, desc: boolean}[] = []
	private _order: {query: QueryExpr, desc: boolean}[] = []
	private _limit?: number

	constructor(mysql: MysqlPool, table: string){
		this.mysql = mysql
		this._table = table
	}

	fields<U extends {
		[key: string]: QueryExpr | string
	}>(map: U): SelectQuery<Record<keyof U, any>>{
		this._columns = inPlaceTransform(map, query =>
			typeof query === "string" ? {query, args: [] as any[]} as QueryExpr : query)
		return this as any
	}

	columns<T extends string>(columns: T[]): SelectQuery<Record<T, any>>{
		this._columns = {}
		for(const column of columns){
			this._columns[column] = {query: `\`${column}\``, args: []}
		}
		return this as any
	}

	join(method: JoinMethod, table: string, parentColumn: string, childColumn: string = parentColumn, alias: string = table){
		this.addJoin(method, table, alias, {
			query: `\`${this._table}\`.\`${parentColumn}\` = \`${alias}\`.\`${childColumn}\``,
			args: [],
		})
		return this
	}

	addJoin(method: JoinMethod, table: string, alias: string, on: QueryExpr){
		this._joins.push({
			query: `${JoinMethod[method]} JOIN \`${table}\` \`${alias}\` ON ${on}`,
			args: on.args,
		})
		return this
	}

	where(where: Where.Clause){
		this._where = where
		return this
	}

	groupBy(query: QueryExpr, desc: boolean = false){
		this._group.push({query, desc})
		return this
	}

	order(query: QueryExpr, desc: boolean = false){
		this._order.push({query, desc})
		return this
	}

	limit(limit: number){
		this._limit = limit
		return this
	}

	async fetchAll(): Promise<T[]>{
		const query = this.toQuery()
		return await new Promise((resolve, reject) => {
			this.mysql.pool.query(query.query, query.args, (err, rows) => {
				if(err){
					reject(err)
				}else{
					resolve(rows)
				}
			})
		})
	}

	async fetchSingle(): Promise<T | null>{
		const result = await this.fetchAll()
		return result.length > 0 ? result[0] : null
	}

	private toQuery(): QueryExpr{
		const query = {query: "SELECT ", args: []} as QueryExpr

		query.query += biMap(this._columns, (k, v) => k === v.query ? k : `${v.query} AS \`${k}\``).join(", ")
		query.args = query.args.concat(...biMap(this._columns, (k, v) => v.args))

		query.query += ` FROM \`${this._table}\``


		const where = whereClauseToQueryExpr(this._where)
		if(where !== undefined){
			query.query += ` WHERE ${where.query}`
		}

		for(const join of this._joins){
			query.query += " " + join.query
			query.args.push(...join.args)
		}

		if(this._group.length > 0){
			query.query += " GROUP BY "
			query.query += this._group.map(group => group.query.query + (group.desc ? " DESC" : "")).join(", ")
			query.args = query.args.concat(...this._group.map(group => group.query.args))
		}

		if(this._order.length > 0){
			query.query += " ORDER BY "
			query.query += this._order.map(order => order.query.query + (order.desc ? " DESC" : "")).join(", ")
			query.args = query.args.concat(...this._order.map(order => order.query.args))
		}

		if(this._limit !== undefined){
			query.query += ` LIMIT ${this._limit}`
		}

		return query
	}
}

export enum JoinMethod{
	INNER,
	"FULL OUTER",
	CROSS,
	LEFT,
	RIGHT,
}

