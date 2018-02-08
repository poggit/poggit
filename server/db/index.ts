import {dbSelect} from "./select"
import {dbUpdate} from "./update"
import {dbDelete} from "./delete"
import {dbInsert} from "./insert"
import {dbTypes} from "./types"

export namespace db{
	export import types = dbTypes

	export type SelectQuery = dbSelect.SelectQuery
	export const SelectQuery = dbSelect.SelectQuery
	export type ListWhereClause = dbSelect.ListWhereClause
	export const ListWhereClause = dbSelect.ListWhereClause
	export type Join = dbSelect.Join
	export const Join = dbSelect.Join
	export type CaseValue = dbUpdate.CaseValue
	export const CaseValue = dbUpdate.CaseValue

	export const select = dbSelect.select
	export const insert_dup = dbInsert.insert_dup
	export const insert = dbInsert.insert
	export const update = dbUpdate.update
	export const update_bulk = dbUpdate.update_bulk
	export const del = dbDelete.del
}
