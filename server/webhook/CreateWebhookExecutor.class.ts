import {ErrorPromise, WebhookExecutor} from "./WebhookExecutor.class"
import {gh} from "../gh"

export class CreateWebhookExecutor extends WebhookExecutor<gh.wh.CreatePayload>{
	protected getTasks(): ErrorPromise[]{
		// TODO implement
		return []
	}
}
