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

import {asyncMap} from "../../shared/util"
import {Escaped, RulesRenderParam, RulesSection} from "../../view/rules.view"
import {SubmitRulesSection} from "../../view/submit-rules.view"
import {db} from "../db"
import {SubmitRule} from "../model/meta/SubmitRule"
import {RouteHandler} from "../router"
import {RuleNode, toRuleTree} from "./RuleTree"

type RuleData = {text: string, uses?: number}

export const submitRulesHandler: RouteHandler = async(req, res) => {
	const allRules = await db.getRepository(SubmitRule).find({
		loadRelationIds: {
			relations: ["parent"],
		},
		loadEagerRelations: false,
	}) as SubmitRule[]

	const roots = toRuleTree<RuleData>(await asyncMap(allRules, async rule => {
		return {
			id: rule.id,
			parentId: (await rule.parent) as unknown as number,
			leaf: rule.leaf,
			data: {text: rule.title, uses: typeof rule.uses === "number" ? rule.uses : undefined},
		}
	}))

	await res.mux({
		html: () => {
			const paragraphs: RulesSection[] = roots.map(ruleNodeToRuleSection)

			return ({
				name: "submit-rules",
				param: {
					meta: {
						title: "Plugin submission rules",
						description: "Rules for submitting plugins to Poggit",
					},
					rules: {
						title: "Plugin submission rules",
						paragraphs: paragraphs,
					},
				} as RulesRenderParam,
			})
		},
		json: () => roots,
	})
}

function ruleNodeToRuleSection(root: RuleNode<RuleData>): SubmitRulesSection{
	return {
		title: new Escaped(root.data.text),
		uses: root.data.uses,
		paragraphs: root.children.map(ruleNodeToRuleSection),
	}
}
