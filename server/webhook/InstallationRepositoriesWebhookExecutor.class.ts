import {WebhookExecutor} from "./WebhookExecutor.class"
import {gh} from "../gh"
import {db} from "../db"
import MiniRepository = ghTypes.MiniRepository

export class InstallationRepositoriesWebhookExecutor extends WebhookExecutor<gh.wh.InstallationRepositoriesPayload>{
	run(): void{
		// TODO enable repositories_added
		const rows: StringMap<{build: boolean}> = {}
		this.payload.repositories_removed.forEach((repo) => {
			rows[repo.id] = {build: false}
		})
		db.update_bulk("repos", "repoId", rows, "1", [], this.onError, () => this.onComplete)
	}
}
