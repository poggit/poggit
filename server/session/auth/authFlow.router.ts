import {NextFunction, Router} from "express"
import {MyRequest, MyResponse} from "../../extensions"
import {consumeToken} from "../tokens"
import * as request from "request"
import {secrets} from "../../secrets"
import * as query_string from "querystring"
import {Authentication} from "./Authentication.class"
import {gh} from "../../gh"
import User = gh.types.User

export const authFlow = Router()

authFlow.get("/auth", (req: MyRequest, res: MyResponse, next: NextFunction) =>{
	if(!req.query.code || !req.query.state){
		res.redirect("/")
		return
	}
	const code = req.query.code as string
	const state = req.query.state as string
	if(!consumeToken(state)){
		res.status(401).render("error", {
			error: new Error(`Please enable cookies. If you did not click the "Login with GitHub" button on Poggit, take caution -- you may have been redirected from a phishing site.`),
		})
		return
	}

	request.post("https://github.com/login/oauth/access_token", {
		form: {
			client_id: secrets.app.clientId,
			client_secret: secrets.app.clientSecret,
			code: code,
			state: state,
		},
	}, (error, response, body) =>{
		if(error){
			next(error)
			return
		}

		const token = query_string.parse(body).access_token as string
		gh.me(token, (user: User) =>{
			console.log("Login: " + user.login)
			req.session.auth = new Authentication(user.id, user.login, token)
			res.redirect(req.session.persistLoc || "/")
		}, next)

		res.cookie("gamma_logged_in", "true", {
			httpOnly: false,
			expires: new Date(Date.now() + 86400e+3 * 10000), // 10000 days, definitely longer than Poggit's life
			secure: true,
		})
	})
})
