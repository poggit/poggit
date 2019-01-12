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

export type RomanCharset = [[string, string], [string, string], [string, string], string]

export const ROMAN_LOWER: RomanCharset = [["i", "v"], ["x", "l"], ["c", "d"], "m"]
export const ROMAN_UPPER: RomanCharset = [["I", "V"], ["X", "L"], ["C", "D"], "M"]

export function toRoman(number: number, charset: RomanCharset = ROMAN_UPPER){
	let output = ""
	if(number >= 1000){
		const thousands = Math.floor(number / 1000)
		output += charset[3].repeat(thousands)
		number -= 1000 * thousands
	}
	for(let i = 2; i >= 0; i--){
		const unit = Math.pow(10, i)
		let digit = Math.floor(number / unit) % 10
		if(digit === 9){
			output += charset[i][0] + charset[i + 1][0]
		}else if(digit === 4){
			output += charset[i][0] + charset[i][1]
		}else{
			if(digit >= 5){
				output += charset[i][1]
				digit -= 5
			}
			output += charset[i][0].repeat(digit)
		}
	}
	return output
}
