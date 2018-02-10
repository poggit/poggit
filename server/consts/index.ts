import * as ui_lib from "../ui/lib"
import {people} from "./people"
import {secrets} from "../secrets"
import {Release} from "./release"
import {POGGIT} from "../version"

export function initAppLocals(locals: any){
	locals.PoggitConsts = {
		AdminLevel: people.AdminLevel,
		Staff: people.StaffList,
		Release: Release,
		Debug: secrets.meta.debug,
		App: {
			ClientId: secrets.app.clientId,
			AppId: secrets.app.id,
			AppName: secrets.app.slug,
		},
	}
	locals.secrets = secrets
	locals.POGGIT = POGGIT
	locals.lib = ui_lib
}
