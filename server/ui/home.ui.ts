import {MyRequest, MyResponse} from "../extensions"
import {util} from "../util"
import {ThumbnailRelease} from "../release/ThumbnailRelease.class"
import {Config} from "../consts/config"
import {NextFunction} from "express"

export function home_ui(req: MyRequest, res: MyResponse, next: NextFunction){
	res.locals.pageInfo.title = "Poggit"
	res.locals.index = {
		recentReleases: [],
	}

	util.waitAll([
		(complete) =>{
			ThumbnailRelease.fromConstraint((query) =>{
				query.where = "state >= ?"
				query.whereArgs = [Config.MIN_PUBLIC_RELEASE_STATE]
				query.order = "releases.updateTime DESC"
				query.limit = 10
			}, (releases) =>{
				res.locals.index.recentReleases = releases
				complete()
			}, (error) => next(error))
		},
	], () => res.render(req.session.auth !== null ? "home/member" : "home/guest"))
}
