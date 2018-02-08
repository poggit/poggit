import * as request from "request"
import {ghWebhooks} from "./webhooks"

export namespace gh{
	export import types = ghTypes
	import RepoIdentifier = ghTypes.RepoIdentifier

	export import wh = ghWebhooks

	const pathRepo = (repoIdentifier: RepoIdentifier) => typeof repoIdentifier === "number" ?
		`repositories/${repoIdentifier}` : `repos/${repoIdentifier.owner}/${repoIdentifier.name}`
	const hashRepo = (repoIdentifier: RepoIdentifier) => typeof repoIdentifier === "number" ?
		`${repoIdentifier}` : `${repoIdentifier.owner}/${repoIdentifier.name}`

	const permissionCache = {} as {
		[hash: string]: {
			updated: Date
			value: {admin: boolean, push: boolean, pull: boolean}
		}
	}

	export function me(token: string, handler: (user: types.User) => void, error: ErrorHandler){
		get(token, "user", handler, error)
	}

	export function repo(uid: number, token: string, repo: types.RepoIdentifier, handler: (repo: types.Repository) => void, error: ErrorHandler){
		get(token, pathRepo(repo), (repo: types.Repository) =>{
			if(repo.permissions !== undefined){
				permissionCache[`${uid}:${repo.id}`] = permissionCache[`${uid}:${repo.full_name}`] = {
					updated: new Date(),
					value: repo.permissions,
				}
			}

			handler(repo)
		}, error)
	}

	export function testPermission(uid: number, token: string, repoId: types.RepoIdentifier, permission: "admin" | "push" | "pull", consumer: (success: boolean) => void, error: ErrorHandler){
		const hash = `${uid}:${hashRepo(repoId)}`
		if(permissionCache[hash] !== undefined && new Date().getTime() - permissionCache[hash].updated.getTime() < 3600e+3){
			consumer(permissionCache[hash].value[permission])
		}
		repo(uid, token, repoId, () => consumer(permissionCache[`${uid}:${hashRepo(repoId)}`].value[permission]), error)
	}

	function get<R>(token: string, path: string, handle: (r: R) => void, onError: ErrorHandler){
		request.get(`https://api.github.com/${path}`, {
			headers: {
				authorization: `bearer ${token}`,
				accept: [
					"application/vnd.github.v3+json",
					"application/vnd.github.mercy-preview+json", // topics
					"application/vnd.github.machine-man-preview+json", // integrations
					"application/vnd.github.cloak-preview+json", // commit search
					"application/vnd.github.jean-grey-preview+json", // node_id
				].join(","),
				"user-agent": "Poggit/2.0-gamma"
			},
			timeout: 10000,
		}, (error, response, body) =>{
			if(error){
				onError(error)
			}else{
				handle(JSON.parse(body))
			}
		})
	}
}
