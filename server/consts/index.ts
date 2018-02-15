import * as ui_lib from "../ui/lib"
import {People} from "./people"
import {SECRETS} from "../secrets"
import {Release} from "./release"
import {POGGIT} from "../version"

export function initAppLocals(locals: any){
	locals.PoggitConsts = {
		AdminLevel: People.AdminLevel,
		Staff: People.StaffList,
		Release: Release,
		Debug: SECRETS.meta.debug,
		App: {
			ClientId: SECRETS.app.clientId,
			AppId: SECRETS.app.id,
			AppName: SECRETS.app.slug,
		},
	}
	locals.secrets = SECRETS
	locals.POGGIT = POGGIT
	locals.lib = ui_lib
}
