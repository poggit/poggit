import {WriteStream} from "fs"
import {gh} from "../gh"
import {util} from "../util"
import wh = gh.wh
import SimplePromise = util.SimplePromise

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

	start(){
		util.waitAll(this.getTasks().map(ep => ep2sp(ep, this.onError)), this.onComplete)
	}

	private onComplete(): void{
		this.stream.end()
		this._onComplete()
	}

	private onError(error: Error): void{
		this.log(`Error: ${error}`)
		this._onComplete()
	}

	protected abstract getTasks(): ErrorPromise[]
}

export interface ErrorPromise{
	(onComplete: BareFx, onError: ErrorHandler): void
}

function ep2sp(ep: ErrorPromise, eh: ErrorHandler): SimplePromise{
	return (onComplete: BareFx): void =>{
		ep(onComplete, (err) =>{
			eh(err)
			onComplete()
		})
	}
}
