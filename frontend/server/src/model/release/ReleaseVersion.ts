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

import {
	Column,
	Entity,
	Index,
	JoinColumn,
	ManyToMany,
	ManyToOne,
	OneToMany,
	OneToOne,
	PrimaryGeneratedColumn,
} from "typeorm"
import {IReleaseDepExt, IReleaseDepPlugin, IReleaseVersion} from "../../../../../shared/model/release/IReleaseVersion"
import {Build} from "../ci/Build"
import {ApiVersion} from "../pm/ApiVersion"
import {Resource} from "../resource/Resource"
import {Release} from "./Release"
import {ReleaseReview} from "./ReleaseReview"

@Entity()
@Index(["release", "version"], {unique: true})
export class ReleaseVersion implements IReleaseVersion{
	@PrimaryGeneratedColumn() id: number
	@ManyToOne(() => Release, release => release.versions) release: Promise<Release>
	@Column({nullable: true}) version?: string
	@OneToOne(() => Resource) @JoinColumn() artifact: Promise<Resource>
	@Column({type: "timestamp"}) date: Date

	@OneToOne(() => Build) @JoinColumn() build: Promise<Build>
	@Column({type: "mediumtext"}) changelog: string

	@Column() isExperimental: boolean
	@Column() requiresMysql: boolean
	@Column() requiresConfig: boolean
	@Column({type: "text"}) requiresOthers: string

	@Column() isLatestApi: boolean
	@Column() isLatestVersion: boolean

	@ManyToMany(() => ApiVersion) apiVersions: Promise<ApiVersion[]>

	@OneToMany(() => ReleaseDepExt, ext => ext.release) depExtensions: Promise<ReleaseDepExt[]>
	@OneToMany(() => ReleaseDepPlugin, ext => ext.dependent) depPlugins: Promise<ReleaseDepPlugin[]>

	@OneToMany(() => ReleaseReview, review => review.version) reviews: Promise<ReleaseReview[]>
}

@Entity()
export class ReleaseDepExt implements IReleaseDepExt{
	@PrimaryGeneratedColumn() id: number
	@ManyToOne(() => ReleaseVersion, release => release.depExtensions) release: Promise<ReleaseVersion>
	@Column() extension: string
}

@Entity()
export class ReleaseDepPlugin implements IReleaseDepPlugin{
	@PrimaryGeneratedColumn() id: number
	@ManyToOne(() => ReleaseVersion, release => release.depPlugins) dependent: Promise<ReleaseVersion>
	@ManyToOne(() => ReleaseVersion) dependency: Promise<ReleaseVersion>
	@ManyToOne(() => ReleaseVersion) dependencyMax?: Promise<ReleaseVersion>
	@Column() optional: boolean
}
