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

import {Entity, JoinTable, ManyToMany, ManyToOne, OneToMany, PrimaryColumn} from "typeorm"
import {IIndexedClass, IIndexedFunction, IIndexedNamespace} from "../../../../../../shared/model/ci/indexed"
import {Build} from "../Build"

@Entity()
export class IndexedNamespace implements IIndexedNamespace{
	@PrimaryColumn({length: 250}) name: string
	@OneToMany(() => IndexedClass, clazz => clazz.namespace) classes: Promise<IndexedClass[]>

	@ManyToOne(() => IndexedNamespace, ns => ns.children, {nullable: true}) parent: Promise<IndexedNamespace>
	@OneToMany(() => IndexedNamespace, ns => ns.parent) children: Promise<IndexedNamespace[]>
}

@Entity()
export class IndexedClass implements IIndexedClass{
	@ManyToOne(() => IndexedNamespace, {primary: true}) namespace: IndexedNamespace
	@PrimaryColumn({length: 40}) name: string
	@ManyToMany(() => Build) @JoinTable() usages: Promise<Build[]>

	@ManyToMany(() => IndexedClass, c => c.importedFrom) @JoinTable() imports: Promise<IndexedClass[]>
	@ManyToMany(() => IndexedClass, c => c.imports) importedFrom: Promise<IndexedClass[]>

	@OneToMany(() => IndexedFunction, f => f.clazz) functions: Promise<IndexedFunction[]>
}

@Entity()
export class IndexedFunction implements IIndexedFunction{
	@ManyToOne(() => IndexedClass, {primary: true}) clazz: IndexedClass
	@PrimaryColumn({length: 40}) name: string

	@ManyToMany(() => IndexedFunction, f => f.calledFrom) @JoinTable() calls: Promise<IndexedFunction[]>
	@ManyToMany(() => IndexedFunction, f => f.calls) calledFrom: Promise<IndexedFunction[]>
}
