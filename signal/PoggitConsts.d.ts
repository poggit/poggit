declare const PoggitConsts: {
	AdminLevel: {
		[name: string]: number | string,
	}
	Staff: {[name: string]: number}
	Release: {
		State: {
			[id: number]: string,
			DRAFT: 0,
			REJECTED: 1,
			SUBMITTED: 2,
			CHECKED: 3,
			VOTED: 4,
			APPROVED: 5,
			FEATURED: 6,
		},
		Flag: {
			[id: number]: string,
			OBSOLETE: 1,
			"PRE_RELEASE": 2,
			OUTDATED: 4,
			OFFICIAL: 8,
		},
		Author: {
			[id: number]: string,
			COLLABORATOR: 1,
			CONTRIBUTOR: 2,
			TRANSLATOR: 3,
			REQUESTER: 4,
		},
		Category: {
			[id: number]: string,
			General: 1,
			"Admin Tools": 2,
			Informational: 3,
			"Anti-Griefing Tools": 4,
			"Chat-Related": 5,
			Teleportation: 6,
			Mechanics: 7,
			Economy: 8,
			Minigame: 9,
			Fun: 10,
			"World Editing and Management": 11,
			"World Generators": 12,
			"Developer Tools": 13,
			Educational: 14,
			Miscellaneous: 15,
		},
		Permission: {
			[index: number]: {name: string, description: string}
		},
	}
	Debug:boolean
	App: {
		ClientId: string,
		AppId: number,
		AppName: string
	}
}
