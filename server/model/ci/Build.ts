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

import {Column, CreateDateColumn, Entity, Index, JoinColumn, ManyToOne, OneToOne, PrimaryGeneratedColumn} from "typeorm"
import {BuildType} from "../../../shared/consts"
import {IBuild} from "../../../shared/model/ci/IBuild"
import {getEnumNames} from "../../../shared/util"
import {User} from "../gh/User"
import {Resource} from "../resource/Resource"
import {Project} from "./Project"

@Entity()
@Index(["project", "cause", "number"], {unique: true})
export class Build implements IBuild{
	@PrimaryGeneratedColumn() id: number
	@ManyToOne(() => Project) project: Project
	@Column({type: "enum", enum: getEnumNames(BuildType)}) cause: keyof BuildType
	@Column() number: number

	@CreateDateColumn({type: "timestamp"}) created: Date
	@OneToOne(() => Resource) @JoinColumn() resource: Resource

	@Column() branch: string
	@Column({type: "char", length: 40}) sha: string
	@ManyToOne(() => User) triggerUser: User

	@Column({nullable: true}) prHeadRepo: number // Repo ID
	@Column({nullable: true}) prNumber: number

	@Column() path: string
	@Column({type: "longtext"}) log: string
}
