import {NextFunction, Router} from "express"
import {PageInfo} from "./PageInfo.class"
import {POGGIT} from "../version"
import {secrets} from "../secrets"
import * as rand from "randomstring"
import {MyRequest, MyResponse} from "../extensions"
import {home_ui} from "./home.ui"
import {jsQueue} from "./jsQueue.class"
import {ResFile} from "../res/ResFile.class"

export const ui = Router()

ui.use("/", (req: MyRequest, res: MyResponse, next: NextFunction) =>{
	const pageInfo = res.locals.pageInfo
		= new PageInfo("https://poggit.pmmp.io" + req.path, secrets.meta.debug ? rand.generate({
		length: 5,
		charset: "alphanumeric",
		readable: true,
	}) : POGGIT.GIT_COMMIT)

	res.locals.sessionData = req.session.toSessionData()

	res.locals.js = new jsQueue

	res.locals.css = (file) =>{
		return new ResFile("res", "res", file, "css", !secrets.meta.debug).html(pageInfo.resSalt)
	}

	next()
})

ui.get("/", home_ui)
