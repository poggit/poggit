import {ErrorPromise, WebhookExecutor} from "./WebhookExecutor.class"
import {gh} from "../gh"

export class InstallationWebhookExecutor extends WebhookExecutor<gh.wh.InstallationPayload>{
	getTasks(): ErrorPromise[]{
		// nothing to do!
		return []
	}
}
