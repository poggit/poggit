export namespace People{
	export enum AdminLevel {
		GUEST = 0,
		MEMBER = 1,
		CONTRIBUTOR = 2,
		MODERATOR = 3,
		REVIEWER = 4,
		ADM = 5,
	}

	export const StaffList: StringMap<number> = {
		"awzaw": AdminLevel.ADM,
		"brandon15811": AdminLevel.ADM,
		"dktapps": AdminLevel.ADM,
		"humerus": AdminLevel.ADM,
		"intyre": AdminLevel.ADM,
		"sof3": AdminLevel.ADM,
		"99leonchang": AdminLevel.REVIEWER,
		"falkirks": AdminLevel.REVIEWER,
		"jacknoordhuis": AdminLevel.REVIEWER,
		"knownunown": AdminLevel.REVIEWER,
		"pemapmodder": AdminLevel.REVIEWER,
		"robske110": AdminLevel.REVIEWER,
		"thedeibo": AdminLevel.REVIEWER,
		"thunder33345": AdminLevel.REVIEWER,
	}

	export function getAdminLevel(name?: string): number{
		return name ? StaffList[name.toLowerCase()] ? StaffList[name.toLowerCase()] : AdminLevel.MEMBER : AdminLevel.GUEST
	}
}
