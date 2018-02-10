import {Router} from "express"
import {createHmac, Hmac} from "crypto"
import {secrets} from "../secrets"
import * as body_parser from "body-parser"
import {db} from "../db"
import {gh} from "../gh"
import {resources} from "../workspace"
import {WebhookExecutor} from "./WebhookExecutor.class"
import {AccessFilter} from "../workspace/AccessFilter.class"
import {RepoAccessFilter} from "../workspace/RepoAccessFilter.class"
import {CreateWebhookExecutor} from "./CreateWebhookExecutor.class"
import {RepositoryWebhookExecutor} from "./RepositoryWebhookExecutor.class"
import {PullRequestWebhookExecutor} from "./PullRequestWebhookExecutor.class"
import {PushWebhookExecutor} from "./PushWebhookExecutor.class"
import {InstallationWebhookExecutor} from "./InstallationWebhookExecutor.class"
import {InstallationRepositoriesWebhookExecutor} from "./InstallationRepositoriesWebhookExecutor.class"
import {createWriteStream, WriteStream} from "fs"
import wh = gh.wh

export const webhookRouter = Router()

webhookRouter.use(body_parser.json({
	verify: (req, res, buf) =>{
		const inputSignature = req.headers["x-hub-signature"]  as string
		const hmac: Hmac = createHmac("sha1", secrets.app.webhookSecret)
		hmac.update(buf)
		const hash: string = hmac.digest("hex")
		if(`sha1=${hash}` !== inputSignature){
			throw new Error("Invalid signature!")
		}
	},
}))
webhookRouter.post("/", (req, res, next) =>{
	const delivery = req.headers["x-github-delivery"] as string
	const event = req.headers["x-github-event"] as "installation" | "installation_repositories" | "repository" | "push" | "pull_request" | "create" | "ping"
	if(event === "ping"){
		res.send("pong")
		return
	}

	const accessFilters: AccessFilter[] = []

	const payload = req.body as gh.wh.Payload
	if(gh.wh.isRepoPayload(payload)){
		const repo: gh.types.Repository = payload.repository
		accessFilters.push(new RepoAccessFilter(repo.id, "admin"))
	}else if(gh.wh.isOrgPayload(payload)){
		const org: gh.types.WHOrganization = payload.organization
	}

	resources.create("log", "text/plain", "poggit.webhook.log", 86400e+3 * 7, accessFilters, (resourceId, file) =>{
		const stream = createWriteStream(file)
		stream.on("open", () =>{
			db.insert("INSERT INTO webhook_executions (deliveryId, logRsr) VALUES (?, ?)", [delivery, resourceId], next, () =>{
				const exec = createWebhookExecutor(event, stream, payload, () =>{
				})
				if(exec === null){
					next(new Error(`Unsupported event ${event}`))
					return
				}
				exec.start()
				res.status(202).set("Content-Type", "text/plain").send("Started")
			})
		})
	}, next)
})

function createWebhookExecutor(event: wh.supported_events, logFile: WriteStream, payload: wh.Payload, onComplete: BareFx): WebhookExecutor<any> | null{
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

	return null
}
