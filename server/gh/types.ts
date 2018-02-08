declare namespace ghTypes{
	interface User{
		id: number
		login: string
		type: "User" | "Organization"
	}

	interface WHOrganization{ // not World Health Organization
		login: string
		id: number
		description: string
	}

	interface MiniRepository{
		id: number
		name: string
		full_name: string
	}

	interface Repository extends MiniRepository{
		owner: User
		private: boolean
		fork: boolean
		archived: boolean
		created_at: string
		updated_at: string
		pushed_at: string
		license: License
		permissions?: {pull: boolean, push: boolean, admin: boolean}
	}

	interface License{
		key: string
		name: string
		spdx_id: string
		url: string
	}

	interface Installation{
		id: number
		account: User
		repository_selection: "selected" | "all"
	}

	type RepoIdentifier = number | {owner: string, name: string}
}
