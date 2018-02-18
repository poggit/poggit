import {Session} from "../../../session/Session.class"
import {DetailedRelease} from "../../../release/DetailedRelease.class"

export class ReleasePerm{
	private session: Session
	private release: DetailedRelease

	constructor(session: Session, release: DetailedRelease){
		this.session = session
		this.release = release
	}

	canEdit(): boolean{
		return true // TODO
	}

	canReview(): boolean{
		return true // TODO
	}

	canAssign(): boolean{
		return true // TODO
	}
}
