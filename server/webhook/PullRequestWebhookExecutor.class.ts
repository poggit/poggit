import {ErrorPromise, WebhookExecutor} from "./WebhookExecutor.class"
import {gh} from "../gh"

export class PullRequestWebhookExecutor extends WebhookExecutor<gh.wh.PullRequestPayload>{
	getTasks(): ErrorPromise[]{
		// TODO
		return []
	}
}
