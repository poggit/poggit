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

import {Column, Entity, ManyToOne, PrimaryGeneratedColumn} from "typeorm"
import {IReleaseReview} from "../../../shared/model/release/IReleaseReview"
import {User} from "../gh/User"
import {ReleaseVersion} from "./ReleaseVersion"

@Entity()
export class ReleaseReview implements IReleaseReview{
	@PrimaryGeneratedColumn() id: number
	@ManyToOne(() => ReleaseVersion, version => version.reviews) version: Promise<ReleaseVersion>
	@ManyToOne(() => User) user: Promise<User>
	@Column() totalScore: number
	@Column({nullable: true}) codeScore?: number
	@Column({nullable: true}) perfScore?: number
	@Column({nullable: true}) usefulScore?: number
	@Column({nullable: true}) ideaScore?: number
	@Column({type: "text"}) message: string
}
