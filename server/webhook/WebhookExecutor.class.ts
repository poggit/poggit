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

	static create(event: wh.supported_events, logFile: string, payload: wh.Payload, onComplete: BareFx): WebhookExecutor<any>{
		if(event === "ping"){
			throw new Error("Cannot create webhook executor for ping event")
		}

		switch(event){
			case "installation":
				return new InstallationWebhookExecutor(logFile, payload as wh.InstallationPayload, onComplete)
			case "installation_repositories":
				return new InstallationRepositoriesWebhookExecutor(logFile, payload as wh.InstallationRepositoriesPayload, onComplete)
			case "repository":
				return new RepositoryWebhookExecutor(logFile, payload as wh.RepositoryPayload, onComplete)
			case "push":
				return new PushWebhookExecutor(logFile, payload as wh.PushPayload, onComplete)
			case "pull_request":
				return new PullRequestWebhookExecutor(logFile, payload as wh.PullRequestPayload, onComplete)
			case "create":
				return new CreateWebhookExecutor(logFile, payload as wh.CreatePayload, onComplete)
		}
		throw new TypeError(`Unsupported event "${event}"`)
	}

	// noinspection TypeScriptAbstractClassConstructorCanBeMadeProtected
	public constructor(logFile: string, payload: P, onComplete: BareFx){
		this._onComplete = onComplete
		this.stream = createWriteStream(logFile)
		this.payload = payload
	}

	log(message: string): void{
		this.stream.write(message + "\n")
	}

	onComplete(): void{
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
