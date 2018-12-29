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

module.exports = {
	debug: true,
	database: {
		host: "mysql",
		username: "poggit",
		password: "correct horse battery staple",
		schema: "poggit",
		port: 3306,
	},
	github: {
		app: {
			id: 221,
			slug: "poggit",
			privateKey: "/path/to/private/key.pem",
		},
		oauth: {
			clientId: "Iv1.89b1944d93f84c7c",
			clientSecret: "0000000000000000000000000000000000000000",
		},
		webhookSecret: "",
	},
};
