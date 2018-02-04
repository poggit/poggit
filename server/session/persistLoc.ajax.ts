import {NextFunction} from "express"
import {MyRequest, MyResponse} from "../extensions"
import {generateToken, LENGTH_FLOW} from "./tokens"

export function persistLocAjax(req: MyRequest, res: MyResponse, next: NextFunction){
	req.session.persistLoc = req.requireString("path")
	generateToken(LENGTH_FLOW, next, (token) =>{
		res.ajaxSuccess({state: token})
	})
}
