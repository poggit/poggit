import * as fs from "fs"
import * as path from "path"

interface NumericStringMap<T> extends StringMap<T>{
}

type ISecrets = {
	meta: {
		debug: boolean
	}
	app: {
		id: number
		urlName: string
		clientId: string
		clientSecret: string
		webhookSecret: string
	}
	mysql: {
		host: string
		user: string
		password: string
		schema: string
		port: number
		poolSize: number
		timeout: number
	}
	perms: {
		submoduleQuota: NumericStringMap<number>
		buildQuota: NumericStringMap<number>
		zipballSize: NumericStringMap<number>
		bans: NumericStringMap<string>
	}
	discord: {
		serverInvite: string
		pluginUpdatesHook: string
		newBuildsHook: string
		errorHook: string
	}
}

export const secrets: ISecrets = JSON.parse(fs.readFileSync(path.join(__dirname, "..", "secret", "secrets.json")).toString("utf8"))
