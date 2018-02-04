import {MyRequest, MyResponse} from "../extensions"
import {NextFunction} from "express"

export function logoutAjax(req: MyRequest, res: MyResponse, next: NextFunction){
	req.session.auth = null
	res.ajaxSuccess({})
}
