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
		db.insert("INSERT INTO webhook_executions (deliveryId, logRsr) VALUES (?, ?)", [delivery, resourceId], next, () =>{
			WebhookExecutor.create(event, file, payload, () =>{
			}).start()
			res.status(202).set("Content-Type", "text/plain").send("Started")
		})

	}, next)
})
