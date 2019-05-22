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

import {createPool, Pool} from "mysql"
import {biMap, HashMap} from "poggit-eps-lib-all/src/HashMap"
import {Logger} from "poggit-eps-lib-all/src/Logger"
import {Config} from "../config"
import {SelectQuery} from "./select"
import {Where, whereClauseToQueryExpr} from "./where"

export class MysqlPool{
	logger: Logger
	config: Config
	pool: Pool

	constructor(logger: Logger, config: Config){
		this.logger = logger
		this.config = config
		this.pool = createPool(Object.assign({}, config.mysql))
	}

	select(table: string){
		return new SelectQuery(this, table)
	}

	insert(table: string, row: HashMap<any>): Promise<number>
	insert(table: string, row: HashMap<any>, odku: true, except: string[]): Promise<number>
	async insert(table: string, row: HashMap<any>, odku: boolean = false, except: string[] = [], extraOdku: HashMap<QueryExpr> = {}): Promise<number>{
		const columns = [] as string[]
		const ph = [] as string[]
		const values = [] as any[]
		for(const column in row){
			columns.push(`\`${column}\``)
			const value = row[column]
			if(typeof value === "object" && typeof value.query === "string" && value.args instanceof Array){
				ph.push(value.query)
				values.push(...value.args)
			}else{
				ph.push("?")
				values.push(value)
			}
		}
		let query = `INSERT INTO \`${table}\` (${columns.join(", ")}) VALUES (${ph.join(", ")})`

		if(odku){
			query += " ON DUPLICATE KEY UPDATE "
			const updates = [] as string[]
			for(const column in row){
				if(except.includes(column)){
					continue
				}
				const value = row[column]
				if(typeof value === "object" && typeof value.query === "string" && value.args instanceof Array){
					updates.push(`\`${column}\` = ${value.query}`)
					values.push(...value.args)
				}else{
					updates.push(`\`${column}\` = ?`)
					values.push(value)
				}
			}
			for(const column in extraOdku){
				updates.push(`\`${column}\` = (${extraOdku[column].query})`)
				values.push(...extraOdku[column].args)
			}
			query += `ON DUPLICATE KEY UPDATE ${updates.join(", ")}`
		}

		return await new Promise((resolve, reject) => {
			this.pool.query(query, values, (err, result) => {
				if(err){
					reject(err)
				}else{
					resolve(result.insertId)
				}
			})
		})
	}

	async insertMulti(table: string, rows: HashMap<any>[]): Promise<void>{
		if(rows.length === 0){
			return
		}

		const columns = [] as string[]
		const phList = [] as string[]
		const values = [] as any[]

		for(const column in rows[0]){
			columns.push(`\`${column}\``)
		}
		for(const row of rows){
			const ph = [] as string[]
			for(const column in rows[0]){
				const value = row[column]
				if(typeof value === "object" && typeof value.query === "string" && value.args instanceof Array){
					ph.push(value.query)
					values.push(...value.args)
				}else{
					ph.push("?")
					values.push(value)
				}
			}
			phList.push(`(${ph.join(", ")})`)
		}
		let query = `INSERT INTO \`${table}\` (${columns.join(", ")}) VALUES (${phList.join(", ")})`

		return await new Promise((resolve, reject) => {
			this.pool.query(query, values, (err) => {
				if(err){
					reject(err)
				}else{
					resolve()
				}
			})
		})
	}

	async update(table: string, fields: HashMap<any>, where: Where.Clause): Promise<number>{
		const values = [] as any[]
		const set = biMap(fields, (k, v) => {
			if(typeof v === "object" && typeof v.query === "string" && v.args instanceof Array){
				values.push(...v.args)
				return `\`${k}\` = ${v.query}`
			}else{
				values.push(v)
				return `\`${k}\` = ?`
			}
		})

		const query: QueryExpr = {
			query: `UPDATE \`${table}\` SET ${set.join(", ")}`,
			args: values,
		}

		const whereQuery = whereClauseToQueryExpr(where);
		if(whereQuery !== undefined){
			query.query += ` WHERE ${whereQuery.query}`
			query.args.push(...whereQuery.args)
		}

		return await new Promise((resolve, reject) => {
			this.pool.query(query.query, query.args, (err, result) => {
				if(err){
					reject(err)
				}else{
					resolve(result.affectedRows)
				}
			})
		})
	}
}

export interface QueryExpr{
	query: string
	args: any[]
}

export const CURRENT_TIMESTAMP: QueryExpr = {query: "CURRENT_TIMESTAMP", args: []}
