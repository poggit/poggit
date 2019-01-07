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

import {Column, CreateDateColumn, Entity, ManyToOne, OneToOne, PrimaryGeneratedColumn} from "typeorm"
import {IResource} from "../../../shared/model/resource/IResource"
import {Repo} from "../gh/Repo"
import {ResourceBlob} from "./ResourceBlob"

@Entity()
export class Resource implements IResource{
	@PrimaryGeneratedColumn() id: number
	@Column() mime: string
	@CreateDateColumn({type: "timestamp"}) created: Date
	@Column({type: "timestamp"}) expiry: Date
	@ManyToOne(() => Repo, {nullable: true}) requiredRepoView?: Repo
	@Column() content: Buffer
	@Column() downloads: number
	@Column() source: string
	@Column() size: number
	@OneToOne(() => ResourceBlob, blob => blob.resource) blob: ResourceBlob
}
