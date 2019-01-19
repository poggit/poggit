/*
 * Poggit-Delta
 *
 * Copyright (C) 2018-2019 Poggit
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

import {ROMAN_LOWER, toRoman} from "../../../../shared/util/roman"

export function toOrdered(i: number, depth: number){
	switch(depth){
		case 0:
			return String.fromCharCode("A".charCodeAt(0) as number + i)
		case 1:
			return (i + 1).toString()
		case 2:
			return String.fromCharCode("a".charCodeAt(0) as number + i)
		default:
			return toRoman(i + 1, ROMAN_LOWER)
	}
}

export interface Rule<Data>{
	id: number
	parentId: number | null
	leaf: boolean
	data: Data
}

export interface RuleNode<Data>{
	id: string
	data: Data
	children: RuleNode<Data>[]
	depth: number
}

export function toRuleTree<D>(rules: Rule<D>[], nodes: {[id: number]: RuleNode<D>} = {}): RuleNode<D>[]{
	rules = rules.sort((a, b) => Math.sign(a.id - b.id))

	const roots: RuleNode<D>[] = []

	let remaining = rules
	while(remaining.length > 0){
		const rem2 = [] as Rule<D>[]
		for(const rule of remaining){
			if(rule.parentId == null || nodes[rule.parentId] !== undefined){
				const parentArray = rule.parentId != null ? nodes[rule.parentId].children : roots
				const parentId = rule.parentId != null ? nodes[rule.parentId].id : ""
				const depth = rule.parentId != null ? (nodes[rule.parentId].depth + 1) : 0
				const r = {
					id: parentId + toOrdered(parentArray.length, depth),
					data: rule.data,
					children: [],
					depth: depth,
				}
				nodes[rule.id] = r
				parentArray.push(r)
			}else{
				rem2.push(rule)
			}
		}

		if(remaining.length === rem2.length){
			throw new Error("Some parents cannot be resolved: " + remaining.map(rule => JSON.stringify(rule.parentId)).join(", "))
		}
		remaining = rem2
	}

	for(const nodeId in nodes){
		delete nodes[nodeId].depth
		if(nodes[nodeId].children.length === 0){
			delete nodes[nodeId].children
		}
	}

	return roots
}
