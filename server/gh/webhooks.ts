import {gh} from "./index"

export namespace ghWebhooks{
	import types = gh.types
	import MiniRepository = ghTypes.MiniRepository

	export type supported_events =
		"installation"
		| "installation_repositories"
		| "repository"
		| "push"
		| "pull_request"
		| "create"
		| "ping"

	export interface Payload{
		sender: types.User
	}

	export interface OrgPayload extends Payload{
		organization: types.WHOrganization
	}

	export interface RepoPayload extends Payload{
		repository: types.Repository
	}

	export interface AppPayload extends Payload{
		installation: types.Installation
	}

	export function isOrgPayload(payload: Payload): payload is OrgPayload{
		return (payload as OrgPayload).organization !== undefined
	}

	export function isRepoPayload(payload: Payload): payload is RepoPayload{
		return (payload as RepoPayload).repository !== undefined
	}

	export function isAppPayload(payload: Payload): payload is AppPayload{
		return (payload as AppPayload).installation !== undefined
	}

	export interface InstallationPayload extends AppPayload{
		action: "created" | "deleted"
	}

	export interface InstallationRepositoriesPayload extends AppPayload{
		action: "added" | "removed"
		repository_selection: "selected" | "all"
		repositories_added: MiniRepository[]
		repositories_removed: MiniRepository[]
	}

	export interface RepositoryPayload extends Payload{
	}

	export interface PushPayload extends Payload{
	}

	export interface PullRequestPayload extends Payload{
	}

	export interface CreatePayload extends Payload{
	}
}
