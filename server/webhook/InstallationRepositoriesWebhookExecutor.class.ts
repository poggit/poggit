import {WebhookExecutor} from "./WebhookExecutor.class"
import {gh} from "../gh"
import {db} from "../db"
import app = gh.app

export class InstallationRepositoriesWebhookExecutor extends WebhookExecutor<gh.wh.InstallationRepositoriesPayload>{
	run(): void{
		app.asInstallation(this.payload.installation.id, (token) =>{
			gh.graphql.repoData<{databaseId: number, isPrivate: boolean}>(token, this.payload.repositories_added
					.map((repo) => ({owner: this.payload.installation.account.login, name: repo.name})),
				`databaseId isPrivate`, (repos) =>{
					const rows = repos.map((repo) => {
						return new db.InsertRow({repoId: repo.databaseId}, {
							owner: repo._repo.owner,
							name: repo._repo.name,
							private: repo.isPrivate,
							build: true,
							installation: this.payload.installation.id
						})
					})
					db.insert_dup("repos", rows, this.onError)
				}, this.onError)
		}, this.onError)

		const rows: StringMap<{build: boolean}> = {}
		this.payload.repositories_removed.forEach((repo) => {
			rows[repo.id] = {build: false}
		})
		db.update_bulk("repos", "repoId", rows, "1", [], this.onError, () => this.onComplete)
	}
}
