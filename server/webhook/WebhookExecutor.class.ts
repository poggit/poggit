import {createWriteStream, WriteStream} from "fs"
import {gh} from "../gh"
import {InstallationWebhookExecutor} from "./InstallationWebhookExecutor.class"
import {InstallationRepositoriesWebhookExecutor} from "./InstallationRepositoriesWebhookExecutor.class"
import {RepositoryWebhookExecutor} from "./RepositoryWebhookExecutor.class"
import {PushWebhookExecutor} from "./PushWebhookExecutor.class"
import {PullRequestWebhookExecutor} from "./PullRequestWebhookExecutor.class"
import {CreateWebhookExecutor} from "./CreateWebhookExecutor.class"
import wh = gh.wh

export abstract class WebhookExecutor<P extends wh.Payload>{
	private readonly stream: WriteStream
	protected readonly payload: P
	private readonly _onComplete: BareFx

	// noinspection TypeScriptAbstractClassConstructorCanBeMadeProtected
	public constructor(logFile: WriteStream, payload: P, onComplete: BareFx){
		this._onComplete = onComplete
		this.stream = logFile
		this.payload = payload
	}

	log(message: string): void{
		this.stream.write(message + "\n")
	}

	onComplete(): void{
		this.stream.end()
		this._onComplete()
	}

	start(){
		this.run()
	}

	onError(error: Error): void{
		this.log(`Error: ${error}`)
		this._onComplete()
	}

	protected abstract run(): void
}
