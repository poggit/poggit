import {AccessFilter} from "./AccessFilter.class"
import {POGGIT} from "../version"
import {db} from "../db"
import * as path from "path"

export function createResource(type: string, mime: string, src: string, duration: number, accessFilters: AccessFilter[], consumer: (resourceId: number, file: string) => void, onError: ErrorHandler){
	db.insert("INSERT INTO resources (type, mimeType, accessFilters, duration, src) VALUES (?, ?, ?, ?, ?)",
		[type, mime, JSON.stringify(accessFilters), duration / 1000, src], onError, (resourceId) =>{
			consumer(resourceId, path.join(POGGIT.INSTALL_ROOT, "resources", Math.floor(resourceId / 1000).toString(), `${resourceId}.${type}`))
		})
}
