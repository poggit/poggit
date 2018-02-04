import * as request from "request"

export namespace gh{
	export function me(token: string, handler: (user: ghTypes.User) => void, error: ErrorHandler){
		get(token, "user", handler, error)
	}

	function get<R>(token: string, path: string, handle: (r: R) => void, onError: ErrorHandler){
		request.get(`https://api.github.com/${path}`, {
			headers: {
				authorization: `bearer ${token}`,
				accept: [
					"application/vnd.github.v3+json",
					"application/vnd.github.mercy-preview+json", // topics
					"application/vnd.github.machine-man-preview+json", // integrations
					"application/vnd.github.cloak-preview+json", // commit search
					"application/vnd.github.jean-grey-preview+json", // node_id
				].join(","),
				"user-agent": "Poggit/2.0-gamma"
			},
			timeout: 10000,
		}, (error, response, body) =>{
			if(error){
				onError(error)
			}else{
				handle(JSON.parse(body))
			}
		})
	}
}
