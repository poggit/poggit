export class PageInfo{
	title: string
	description = "Release and development platform for PocketMine plugins"
	keywords: string[] = ["PocketMine", "plugin", "PocketMine plugins", "PMMP plugins", "MCPE plugins", "Minecraft PE plugins"] // just copied from the Search Console top queries
	type = "website"
	url: string
	resSalt: string

	constructor(url: string, resSalt: string){
		this.url = url
		this.resSalt = resSalt
	}
}
