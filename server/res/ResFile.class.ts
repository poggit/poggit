import {POGGIT} from "../version"
import * as fs from "fs"
import * as path from "path"
import {Config} from "../consts/config"

const fileSizeCache = {} as StringMap<string | null>

export type minimizable_ext = "js" | "css"

export class ResFile{
	readonly dir: string
	readonly internal: string
	readonly name: string
	readonly ext: minimizable_ext
	readonly min: boolean

	readonly pathName: string
	readonly file: string

	constructor(dir: string, internal: string, name: string, ext: minimizable_ext, min: boolean){
		this.dir = dir
		this.internal = internal
		this.name = name
		this.ext = ext
		this.min = min
		const file = `${this.name}${this.min ? ".min" : ""}.${this.ext}`
		this.pathName = `/${this.dir}/${file}`
		this.file = path.join(POGGIT.INSTALL_ROOT, internal, file)
	}

	getPathName(): string{
		return this.pathName
	}

	getFile(): string{
		return this.file
	}

	html(salt: string): string{
		let cache
		if(fileSizeCache[this.getFile()] === undefined){
			cache = fileSizeCache[this.getFile()]
				= fs.statSync(this.getFile()).size > Config.MAX_INLINE_SIZE ? null : fs.readFileSync(this.getFile()).toString()
		}else{
			cache = fileSizeCache[this.getFile()]
		}
		if(this.ext === "js"){
			return cache === null ?
				`<script src='${this.getPathName()}/${this.name.replace(".", "Dot").replace("/", "Slash") + salt}'></script>` :
				`<script>${cache}</script>`
		}
		if(this.ext === "css"){
			return cache === null ?
				`<link rel="stylesheet" type="text/css" href="${this.getPathName()}/${salt}"/>` :
				`<style>${cache}</style>`
		}
		return "never"
	}
}
