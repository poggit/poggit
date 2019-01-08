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
	CreateDateColumn,
	Entity,
	ManyToOne,
	OneToMany,
	OneToOne,
	PrimaryColumn,
	PrimaryGeneratedColumn,
	UpdateDateColumn,
} from "typeorm"
import {IUser, IUserIp} from "../../../shared/model/gh/IUser"
import {Project} from "../ci/Project"
import {Repo} from "./Repo"
import {UserConfig} from "./UserConfig"

@Entity()
export class User implements IUser{
	@PrimaryColumn() id: number
	@Column({unique: true}) name: string
	@Column() registered: boolean
	@Column() isOrg: boolean
	@Column() email: string
	@CreateDateColumn({type: "timestamp"}) firstLogin: Date
	@Column({type: "timestamp"}) lastLogin: Date
	@OneToOne(() => UserConfig, config => config.user) config: Promise<UserConfig>

	@OneToMany(() => Repo, repo => repo.owner) repos: Promise<Repo[]>
	@OneToMany(() => Project, project => project.owner) projects: Promise<Project[]>

	@OneToMany(() => UserIp, ip => ip.user) ips: Promise<UserIp[]>
}

@Entity()
export class UserIp implements IUserIp{
	@PrimaryGeneratedColumn() id: number
	@ManyToOne(() => User, user => user.ips) user: Promise<User>
	@Column() ip: string
	@UpdateDateColumn({type: "timestamp"}) date: Date
}
