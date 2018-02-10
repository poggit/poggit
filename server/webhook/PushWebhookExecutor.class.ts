import {ErrorPromise, WebhookExecutor} from "./WebhookExecutor.class"
import {gh} from "../gh"

export class PushWebhookExecutor extends WebhookExecutor<gh.wh.PushPayload>{
	protected getTasks(): ErrorPromise[]{
		// TODO implement
		return []
	}
}
