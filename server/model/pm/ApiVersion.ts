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

import {Column, Entity, ManyToOne, OneToMany, PrimaryGeneratedColumn} from "typeorm"
import {IApiVersion, IApiVersionDescription} from "../../../shared/model/pm/IApiVersion"

@Entity()
export class ApiVersion implements IApiVersion{
	@PrimaryGeneratedColumn() id: number
	@Column({unique: true}) api: string
	@Column() incompatible: boolean
	@Column() minimumPhp: string
	@Column() downloadLink: string
	@OneToMany(() => ApiVersionDescription, desc => desc.version) description: Promise<ApiVersionDescription[]>
}

@Entity()
export class ApiVersionDescription implements IApiVersionDescription{
	@PrimaryGeneratedColumn() id: number
	@ManyToOne(() => ApiVersion) version: Promise<ApiVersion>
	@Column({type: "tinytext"}) value: string
}
