import * as fs from "fs"
import * as path from "path"

const head = path.join(__dirname, "..", ".git", ".HEAD")

let sha = "0000000", branch = ""

if(fs.existsSync(head)){
	let contents: string = fs.readFileSync(head).toString("utf8")
	if(contents.charAt(contents.length - 1) === "\n"){
		contents = contents.substr(0, contents.length - 1)
	}
	if(/^[0-9a-f]{40}$/i.test(contents)){
		sha = contents.toLowerCase()
	}else if(contents.indexOf("ref: ") === 0){
		branch = contents.split("/", 3)[2]
		const ref = path.join(__dirname, "..", ".git", contents.substr(5))
		if(fs.existsSync(ref)){
			sha = fs.readFileSync(ref).toString("utf8")
			if(sha.charAt(sha.length - 1) === "\n"){
				sha = sha.substr(0, sha.length - 1)
			}
		}
	}
}

export const POGGIT = {
	VERSION: "2.0-gamma",
	INSTALL_ROOT: path.join(__dirname, ".."),
	GIT_COMMIT: sha,
	GIT_BRANCH: branch,
}
