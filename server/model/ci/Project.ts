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

import {Column, Entity, Index, ManyToOne, OneToMany, OneToOne, PrimaryGeneratedColumn} from "typeorm"
import {IProject} from "../../../shared/model/ci/IProject"
import {Repo} from "../gh/Repo"
import {User} from "../gh/User"
import {Release} from "../release/Release"
import {Build} from "./Build"

@Entity()
@Index(["owner", "name"], {unique: true})
export class Project implements IProject{
	@PrimaryGeneratedColumn() id: number
	@ManyToOne(() => User, user => user.projects) owner: User
	@Column() name: string

	@ManyToOne(() => Repo) repo: Repo
	@OneToMany(() => Build, build => build.project) builds: Build[]
	@OneToOne(() => Release, release => release.project, {nullable: true}) release?: Release
}
