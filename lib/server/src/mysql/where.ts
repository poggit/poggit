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

import {QueryExpr} from "./index"

export function whereClauseToQueryExpr(clause: Where.Clause): QueryExpr | undefined{
	return (clause instanceof Where.Class) ? clause.toQueryExpr() : (
		typeof clause === "string" ? {query: clause, args: []} : undefined)
}

export namespace Where{
	export type Clause = string | undefined | Class

	export abstract class Class{
		abstract toQueryExpr(): QueryExpr | undefined
	}

	class Operated extends Class{
		private readonly operator: string
		private readonly operands: Clause[]

		constructor(operator: string, operands: Clause[]){
			super()
			this.operator = operator
			this.operands = operands
		}

		toQueryExpr(): QueryExpr | undefined{
			const queryExprs = this.operands.map(whereClauseToQueryExpr).filter(t => t !== undefined) as QueryExpr[]
			if(queryExprs.length === 0){
				return undefined
			}
			return {
				query: queryExprs.map(queryExpr => `(${queryExpr.query})`).join(` ${this.operator} `),
				args: ([] as any[]).concat(...queryExprs.map(queryExpr => queryExpr.args)),
			}
		}
	}

	export function AND(clauses: Clause[]){
		return new Operated("AND", clauses)
	}

	export function OR(clauses: Clause[]){
		return new Operated("AND", clauses)
	}

	class In extends Class{
		private readonly field: string
		private readonly options: any[]

		constructor(field: string, options: any[]){
			super()
			this.field = field
			this.options = options
		}

		toQueryExpr(): QueryExpr | undefined{
			return {
				query: `(${this.field}) IN (${",?".repeat(this.options.length).substr(1)})`,
				args: this.options,
			}
		}
	}

	export function IN(field: string, options: any[]){
		return new In(field, options)
	}

	class Expr extends Class{
		private readonly query: string
		private readonly args: any[]

		constructor(query: string, args: any[]){
			super()
			this.query = query
			this.args = args
		}

		toQueryExpr(): QueryExpr | undefined{
			return {
				query: this.query,
				args: this.args,
			}
		}
	}

	export function EXPR(query: string, ...values: any[]){
		return new Expr(query, values)
	}

	export function EQ(column: string, value: any){
		return new Expr(`\`${column}\` = ?`, [value])
	}
}
