import {WebhookExecutor} from "./WebhookExecutor.class"
import {gh} from "../gh"

export class RepositoryWebhookExecutor extends WebhookExecutor<gh.wh.RepositoryPayload>{
}
