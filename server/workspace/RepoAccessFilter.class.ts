import {AccessFilter} from "./AccessFilter.class"
import {MyRequest, MyResponse} from "../extensions"
import {gh} from "../gh"

export class RepoAccessFilter implements AccessFilter{
	repoId: number
	permission: "admin" | "push" | "pull"

	constructor(repoId: number, permission: "admin" | "push" | "pull" = "admin"){
		this.repoId = repoId
		this.permission = permission
	}

	allow(req: MyRequest, res: MyResponse, next: BareFx, error: ErrorHandler): void{
		const auth = req.session.auth
		if(auth === null){
			error(new Error("You need to login with GitHub to view this resource."))
		}else{
			gh.testPermission(auth.uid, auth.token, this.repoId, this.permission, (success) =>{
				if(success){
					next()
				}else{
					error(new Error(`You need ${this.permission} access to repo #${this.repoId} to view this resource.
For security reasons, we are not going to reveal the name of this repo. Make sure you have logged in to the correct account. You are currently logged in as @${auth.name}.`))
				}
			}, error)
		}
	}
}
