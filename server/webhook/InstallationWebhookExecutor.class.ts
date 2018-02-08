import {WebhookExecutor} from "./WebhookExecutor.class"
import {gh} from "../gh"

export class InstallationWebhookExecutor extends WebhookExecutor<gh.wh.InstallationPayload>{
	run(): void{
		// nothing to do yet
	}
}
