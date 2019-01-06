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

export class PoggitError{
	status: number
	message: string
	details?: string | undefined
	friendly: boolean

	private constructor(status: number, message: string, details: string | undefined, friendly: boolean){
		this.status = status
		this.message = message
		this.details = details
		this.friendly = friendly
	}

	static friendly(message: string, details?: string, status = 400){
		return new PoggitError(status, message, details, true)
	}

	static internal(message: string, details?: string, status = 500){
		return new PoggitError(status, message, details, false)
	}
}
