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

export enum BuildType{
	dev,
	pr,
}

export enum AuthorType{
	requester,
	translator,
	contributor,
	collaborator,
	owner,
}

export enum CategoryType{
	"General",
	"Admin Tools",
	"Informational",
	"Anti-Griefing Tools",
	"Chat-Related",
	"Teleportation",
	"Mechanics",
	"Economy",
	"Minigame",
	"Fun",
	"World Editing and Management",
	"World Generators",
	"Developer Tools",
	"Educational",
	"Miscellaneous",
	"API plugins",
}
