export namespace dbTypes{
	export type QueryArgument = string | number | boolean | Date | Buffer | null
	export type ResultSet<R extends StringMap<CellValue>> = R[]
	export type TableRef = string
	export type WhereClause = string | IWhereClause
	export type WhereArgs = QueryArgument[] | IWhereClause
	export type FieldRef = string | {toString(): string}
	export type FieldList = StringMap<FieldRef>
	export type CellValue = number | Date | Buffer | string | boolean

	export interface IWhereClause{
		toString(): string

		getArgs(): QueryArgument[]
	}
}
