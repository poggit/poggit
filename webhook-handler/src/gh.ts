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

import * as App from "@octokit/app"
import * as Octokit from "@octokit/rest"
import * as fs from "fs"
import {lazyAsync} from "../../shared/lazy"
import {errorPromise} from "../../shared/util"
import {secrets} from "./secrets"

const app = lazyAsync(async() => new App({
	id: secrets.github.app.id,
	privateKey: await errorPromise(cb => fs.readFile(secrets.github.app.privateKey, {encoding: "utf8"}, cb)),
}))

const createOctokit = () => new Octokit({
	headers: {
		accept: "application/vnd.github.v3+json, application/vnd.github.machine-man-preview+json",
	},
})

export async function getClient(installId: number){
	const octokit = createOctokit()
	octokit.authenticate({
		type: "token",
		token: await (await app.get()).getInstallationAccessToken({installationId: installId}),
	})
	return octokit
}
