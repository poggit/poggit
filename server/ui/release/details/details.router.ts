import {NextFunction, Router} from "express"
import {MyRequest, MyResponse} from "../../../extensions"
import {Release} from "../../../consts/release"
import {DetailedRelease} from "./DetailedRelease.class"
import {Config} from "../../../consts/config"
import {ReleasePerm} from "./ReleasePerm.class"

export const details_ui = Router()

details_ui.get("/", (req: MyRequest, res: MyResponse, next: NextFunction) =>{
	if(req.query.releaseId !== undefined){
		const releaseId = parseInt(req.query.releaseId)
		if(releaseId >= 1){ // covers NaN conditions
			specificReleaseId(req, res, next, releaseId)
			return
		}
	}
	res.redirect("/plugins")
})

details_ui.get("/id/:releaseId(\\d+)", (req: MyRequest, res: MyResponse, next: NextFunction) =>{
	specificReleaseId(req, res, next, parseInt(req.params.releaseId))
})

function specificReleaseId(req: MyRequest, res: MyResponse, next: NextFunction, releaseId: number){
	DetailedRelease.fromConstraint((query) =>{
		query.where = "releases.releaseId = ?"
		query.whereArgs = [releaseId]
	}, (releases) =>{
		if(releases.length === 0){
			res.redirect(`/plugins?error=${encodeURIComponent(`The releaseId ${releaseId} does not exist`)}`)
			return
		}
		const release = releases[0]
		if(!Release.canAccessState(req.session.getAdminLevel(), release.state)){
			res.redirect(`/plugins?error=${encodeURIComponent(release.state === Release.State.Rejected ? "This release has been rejected" : "This release is not accessible to you yet")}`)
			return
		}
	}, next)
}

details_ui.get(`/:name(${Release.NAME_PATTERN})`, (req: MyRequest, res: MyResponse, next: NextFunction) =>{
	const name = req.params.name
	const preRelease = req.query.pre !== "off" // display pre-release versions by default, unless ?pre=off // TODO handle this later

	DetailedRelease.fromConstraint((query) =>{
		query.where = "releases.name = ?"
		query.whereArgs = [name]
		query.order = "releases.releaseId DESC"
	}, (releases) =>{
		let highestNewer: {releaseId: number, version: string, state: number} | null = null
		let bestRelease: DetailedRelease | null = null
		for(let i = 0; i < releases.length; ++i){
			if(releases[i].state >= Config.MIN_PUBLIC_RELEASE_STATE){
				bestRelease = releases[i]
				break
			}
			if(Release.canAccessState(req.session.getAdminLevel(), releases[i].state) &&
				(highestNewer === null || highestNewer.state < releases[i].state)){
				highestNewer = {
					releaseId: releases[i].releaseId,
					version: releases[i].version,
					state: releases[i].state,
				}
			}
		}
		if(bestRelease === null){
			res.redirect(`/plugins?term=${encodeURIComponent(name)}&error=${encodeURIComponent(`The plugin ${name} does not exist, or is not visible to you.`)}`)
			return
		}
		if(highestNewer !== null && highestNewer.state < bestRelease.state){
			bestRelease.lowerStateAlt = highestNewer
		}

		displayPlugin(req, res, next, bestRelease)
	}, next)
})

details_ui.get(`/:name(${Release.NAME_PATTERN})/:version(${Release.VERSION_PATTERN})`, (req: MyRequest, res: MyResponse, next: NextFunction) =>{
	const name = req.params.name
	const version = req.params.version

	DetailedRelease.fromConstraint((query) =>{
		query.where = "releases.name = ? AND releases.version = ?"
		query.whereArgs = [name, version]
		query.order = "releases.releaseId DESC"
	}, (releases) =>{
		if(releases.length === 0){
			res.redirect("/plugins?term=" + encodeURIComponent(name))
			return
		}
		if(releases.length === 1){
			displayPlugin(req, res, next, releases[0])
			return
		}

		// TODO choose the non-rejected version, or redirect to /p/id
	}, next)
})

function displayPlugin(req: MyRequest, res: MyResponse, next: NextFunction, release: DetailedRelease){
	const releasePerm = new ReleasePerm(req.session, release)
	res.render("release/details", {release: release, access: releasePerm})
}
