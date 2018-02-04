import {NextFunction, Router} from "express"
import {MyRequest, MyResponse} from "../extensions"
import {join, relative} from "path"
import {POGGIT} from "../version"
import * as fs from "fs"

export function res(dir: string): Router{
	const res: Router = Router()

	res.get("*", (req: MyRequest, res: MyResponse, next: NextFunction) =>{
		const pieces = req.path.substr(1).split("/")
		if(pieces.length){
			if(pieces[pieces.length - 1].indexOf(".") === -1){
				pieces.pop()
			}
			const target = join(POGGIT.INSTALL_ROOT, dir, pieces.join("/"))
			if(relative(join(POGGIT.INSTALL_ROOT), target).replace("\\", "/").indexOf(dir + "/") !== 0){
				res.status(403).set("Content-Type", "text/plain").send("This file is not accessible by you.")
				return
			}
			fs.access(target, fs.constants.R_OK, (err) =>{
				if(err){
					res.status(404).set("Content-Type", "text/plain").send("This file does not exist.")
				}else{
					res.sendFile(target, {
						maxAge: 604800000,
					}, err => next(err))
				}
			})
		}
	})

	return res
}

