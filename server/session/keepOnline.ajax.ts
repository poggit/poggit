import {MyRequest, MyResponse} from "../extensions"
import {NextFunction} from "express"
import {db} from "../db"

export function keepOnlineAjax(req: MyRequest, res: MyResponse, next: NextFunction){
	if(req.session.auth !== null){
		db.insert("INSERT INTO user_ips (uid, ip) VALUES (?, ?) ON DUPLICATE KEY UPDATE time = CURRENT_TIMESTAMP", [req.session.auth.uid, req.realIp], ()=>undefined)
	}
	db.select("SELECT KeepOnline(?, ?) onlineCount", [req.realIp, req.session.auth !== null ? req.session.auth.uid : 0], (result) =>{
		res.ajaxSuccess(result[0].onlineCount)
	}, next)
}
