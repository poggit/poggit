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

import {IBuild} from "../IBuild"

export interface IIndexedNamespace{
	name: string
	classes: Promise<IIndexedClass[]>

	parent: Promise<IIndexedNamespace>
	children: Promise<IIndexedNamespace[]>
}

export interface IIndexedClass{
	namespace: IIndexedNamespace
	name: string
	usages: Promise<IBuild[]>

	imports: Promise<IIndexedClass[]>
	importedFrom: Promise<IIndexedClass[]>

	functions: Promise<IIndexedFunction[]>
}

export interface IIndexedFunction{
	clazz: IIndexedClass
	name: string

	calls: Promise<IIndexedFunction[]>
	calledFrom: Promise<IIndexedFunction[]>
}
