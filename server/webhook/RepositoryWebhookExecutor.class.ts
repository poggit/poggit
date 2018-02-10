import {ErrorPromise, WebhookExecutor} from "./WebhookExecutor.class"
import {gh} from "../gh"
import {db} from "../db"

export class RepositoryWebhookExecutor extends WebhookExecutor<gh.wh.RepositoryPayload>{
	protected getTasks(): ErrorPromise[]{
		return [
			(onComplete, onError) =>{
				if(this.payload.action === "publicized" || this.payload.action === "privatized"){
					db.update("repos", {private: this.payload.action === "privatized"}, "repoId = ?", [this.payload.repository.id], onError, onComplete)
				}else{
					onComplete()
				}
			},
		]

	}
}
