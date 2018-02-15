import {NextFunction, Router} from "express"
import {PageInfo} from "./PageInfo.class"
import {POGGIT} from "../version"
import {SECRETS} from "../secrets"
import * as rand from "randomstring"
import {MyRequest, MyResponse} from "../extensions"
import {home_ui} from "./home.ui"
import {jsQueue} from "./jsQueue.class"
import {ResFile} from "../res/ResFile.class"
import {list_ui} from "./release/list/list.router"
import {details_ui} from "./release/details/details.router"

export const ui = Router()

ui.use("/", (req: MyRequest, res: MyResponse, next: NextFunction) =>{
	const pageInfo = res.locals.pageInfo
		= new PageInfo("https://poggit.pmmp.io" + req.path, SECRETS.meta.debug ? rand.generate({
		length: 5,
		charset: "alphanumeric",
		readable: true,
	}) : POGGIT.GIT_COMMIT)

	res.locals.sessionData = req.session.toSessionData()

	res.locals.js = new jsQueue

	res.locals.css = (file) =>{
		return new ResFile("res", "res", file, "css", !SECRETS.meta.debug).html(pageInfo.resSalt)
	}

	next()
})

ui.get("/", home_ui)

ui.use("/plugins", list_ui)
ui.use("/pi", list_ui)

ui.use("/p", details_ui)
ui.use("/plugin", details_ui)
