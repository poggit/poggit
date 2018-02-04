import {NextFunction} from "express"
import {MyRequest, MyRequestHandler, MyResponse} from "../extensions"
import * as crypto from "crypto"
import {Session} from "./Session.class"

export const SESSION_DURATION = 2 * 60 * 60 * 1000

export const sessions = {} as StringMap<Session>

export const auth: MyRequestHandler = (req: MyRequest, res: MyResponse, next: NextFunction) =>{
	let cookie: string

	if(req.cookies["PoggitSess"] !== undefined){
		cookie = req.cookies["PoggitSess"]
		if(sessions[cookie] !== undefined){
			sessions[cookie].refresh(SESSION_DURATION)
			execute()
			return
		}
	}

	crypto.randomBytes(16, (err: Error, buf: Buffer) =>{
		if(err){
			next(err)
			return
		}
		res.cookie("PoggitSess", cookie = buf.toString("hex"), {
			httpOnly: true,
			sameSite: true,
			secure: true,
			maxAge: SESSION_DURATION,
		})
		sessions[cookie] = new Session(SESSION_DURATION)
		execute()
	})

	function execute(){
		req.session = sessions[cookie]
		if(req.session === null){
			next(new Error("req.session is null"))
		}

		next()
	}
}

export function cleanSessions(){
	for(const cookie in sessions){
		if(sessions[cookie].expired()){
			delete sessions[cookie]
		}
	}
}
