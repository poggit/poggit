declare namespace ghTypes{
	interface User{
		id: number
		login: string
		type: "User" | "Organization"
	}

	interface Repository{
		id: number
		name: string
		full_name: string
		owner: User
		private: boolean
		fork: boolean
		archived: boolean
		created_at: string
		updated_at: string
		pushed_at: string
		license: License
	}

	interface License{
		key: string
		name: string
		spdx_id: string
		url: string
	}
}
