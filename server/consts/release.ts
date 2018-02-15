import {Config} from "./config"
import {People} from "./people"

export namespace Release{
	export const NAME_REGEX: RegExp = /^[A-Za-z0-9_.\-]{2,}$/
	export const NAME_PATTERN: string = NAME_REGEX.toString().substring(2, NAME_REGEX.toString().length - 2)
	export const isValidName = (name: string) => NAME_REGEX.test(name)

	export const VERSION_REGEX: RegExp = /^(0|[1-9]\d*)\.(0|[1-9]\d*)(\.(0|[1-9]\d*))?(-(0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*)(\.(0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*))*)?(\+[0-9a-zA-Z-]+(\.[0-9a-zA-Z-]+)*)?$/

	export const isValidVersion = (version: string) => VERSION_REGEX.test(version)
	export const VERSION_PATTERN: string = NAME_REGEX.toString().substring(2, NAME_REGEX.toString().length - 2)


	export enum State {
		Draft = 0,
		Rejected = 1,
		Submitted = 2,
		Checked = 3,
		Voted = 4,
		Approved = 5,
		Featured = 6
	}

	export enum Flag {
		OBSOLETE = 0x01, // this is not the latest plugin version
		PRE_RELEASE = 0x02, // user flag
		OUTDATED = 0x04, // does not support the latest API version | user flag
		OFFICIAL = 0x08, // admin-only user flag
	}

	export enum Author {
		// a person who is in charge of a major part of the plugin (whether having written code directly or not, or just merely designing the code structure)
		COLLABORATOR = 1,
		// a person who added minor changes to the plugin's code, but is not officially in the team writing the plugin
		CONTRIBUTOR = 2,
		// a person who only contributes translations or other non-code changes for the plugin
		TRANSLATOR = 3,
		// a person who provides abstract ideas for the plugin
		REQUESTER = 4,
	}

	export enum Category {
		"General" = 1,
		"Admin Tools" = 2,
		"Informational" = 3,
		"Anti-Griefing Tools" = 4,
		"Chat-Related" = 5,
		"Teleportation" = 6,
		"Mechanics" = 7,
		"Economy" = 8,
		"Minigame" = 9,
		"Fun" = 10,
		"World Editing and Management" = 11,
		"World Generators" = 12,
		"Developer Tools" = 13,
		"Educational" = 14,
		"Miscellaneous" = 15,
	}

	export const Permission = {
		1: {
			"name": "Manage plugins",
			"description": "installs/uninstalls/enables/disables plugins",
		},
		2: {
			"name": "Manage worlds",
			"description": "registers worlds",
		},
		3: {
			"name": "Manage permissions",
			"description": "only includes managing user permissions for other plugins",
		},
		4: {
			"name": "Manage entities",
			"description": "registers new types of entities",
		},
		5: {
			"name": "Manage blocks/items",
			"description": "registers new blocks/items",
		},
		6: {
			"name": "Manage tiles",
			"description": "registers new tiles",
		},
		7: {
			"name": "Manage world generators",
			"description": "registers new world generators",
		},
		8: {
			"name": "Database",
			"description": "uses databases not local to this server instance, e.g. a MySQL database",
		},
		9: {
			"name": "Other files",
			"description": "uses SQLite databases and YAML data folders. Do not include non-data-saving fixed-number files (i.e. config & lang files)",
		},
		10: {
			"name": "Permissions",
			"description": "registers permissions",
		},
		11: {
			"name": "Commands",
			"description": "registers commands",
		},
		12: {
			"name": "Edit world",
			"description": "changes blocks in a world; do not check this if your plugin only edits worlds using world generators",
		},
		13: {
			"name": "External Internet clients",
			"description": "starts client sockets to the external Internet, including MySQL and cURL calls",
		},
		14: {
			"name": "External Internet sockets",
			"description": "listens on a server socket not started by PocketMine",
		},
		15: {
			"name": "Asynchronous tasks",
			"description": "uses AsyncTask",
		},
		16: {
			"name": "Custom threading",
			"description": "starts threads; does not include AsyncTask (because they aren't threads)",
		},
	}

	export function canAccessState(adminLevel: number, state: number){
		if(adminLevel >= People.AdminLevel.ADM){
			return state >= State.Draft
		}
		if(adminLevel >= People.AdminLevel.REVIEWER){
			return state >= State.Rejected
		}
		if(adminLevel >= People.AdminLevel.MEMBER){
			return state >= State.Checked
		}
		return state >= Config.MIN_PUBLIC_RELEASE_STATE
	}
}
