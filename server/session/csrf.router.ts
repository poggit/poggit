import {NextFunction, Router} from "express"
import {consumeToken, generateToken, LENGTH_AJAX} from "./tokens"
import {MyRequest, MyResponse} from "../extensions"
import {keepOnlineAjax} from "./keepOnline.ajax"
import {persistLocAjax} from "./persistLoc.ajax"
import {logoutAjax} from "./logoutAjax.ajax"
import * as body_parser from "body-parser"

export const csrf = Router()

csrf.post("/", (req: MyRequest, res: MyResponse, next: NextFunction) =>{
	generateToken(LENGTH_AJAX, next, (token) =>{
		res.status(201).set("content-type", "text/plain").send(token)
	})
})

csrf.use(body_parser.json())

csrf.use((req: MyRequest, res: MyResponse, next: NextFunction) =>{
	if(req.headers["x-poggit-csrf"] === undefined){
		res.status(401).set("content-type", "text/plain").send("Missing x-poggit-csrf header")
		return
	}

	const token = req.headers["x-poggit-csrf"]
	if(typeof token !== "string" || !consumeToken(token)){
		res.status(401).set("content-type", "text/plain").send("CSRF token is invalid or has expired")
		return
	}

	req.requireBoolean = (name) =>{
		const ret = req.body[name]
		if(typeof ret !== "boolean"){
			throw `Missing boolean parameter "${name}"`
		}
		return ret
	}
	req.requireNumber = (name) =>{
		const ret = req.body[name]
		if(typeof ret !== "number"){
			throw `Missing number parameter "${name}"`
		}
		return ret
	}
	req.requireString = (name) =>{
		const ret = req.body[name]
		if(typeof ret !== "string"){
			throw new AjaxError(`Missing string parameter "${name}"`)
		}
		return ret
	}

	res.ajaxSuccess = (data: any = {}) =>{
		res.status(200).set("content-type", "application/json").send(JSON.stringify({
			success: true,
			data: data,
		}))
	}
	res.ajaxError = (message: string) =>{
		res.status(200).set("content-type", "application/json").send(JSON.stringify({
			success: false,
			data: message,
		}))
	}

	next()
})

export class AjaxError{
	message: string

	constructor(message: string){
		this.message = message
	}
}

function ajaxDelegate(path: string, delegate: (req: MyRequest, res: MyResponse, next: NextFunction) => void): void{
	csrf.post(`/${path}`, (req: MyRequest, res: MyResponse, next: NextFunction) =>{
		try{
			delegate(req, res, next)
		}catch(thrown){
			if(thrown instanceof AjaxError){
				res.ajaxError(thrown.message)
			}else{
				throw thrown
			}
		}
	})
}

ajaxDelegate("session/online", keepOnlineAjax)
ajaxDelegate("login/persistLoc", persistLocAjax)
ajaxDelegate("login/logout", logoutAjax)
