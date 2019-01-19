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

import {Column, Entity, Index, ManyToOne, PrimaryColumn} from "typeorm"
import {IRepo} from "../../../../../shared/model/gh/IRepo"
import {User} from "./User"

@Entity()
@Index(["owner", "name"], {unique: true})
export class Repo implements IRepo{
	@PrimaryColumn() id: number
	@ManyToOne(() => User, user => user.repos) owner: Promise<User>
	@Column() name: string
	@Column() private: boolean
	@Column() fork: boolean
	@Column() enabled: boolean
}
