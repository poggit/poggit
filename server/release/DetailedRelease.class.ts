import {db} from "../db"
import {util} from "../util"
import {dbTypes} from "../db/types"
import {Release} from "../consts/release"
import {PluginReview} from "../ui/release/PluginReview.class"
import ListWhereClause = db.ListWhereClause
import ResultSet = dbTypes.ResultSet

export class DetailedRelease{
	build: BriefBuildInfo
	releaseId: number
	name: string
	version: string
	submitDate: Date
	approveDate: Date
	artifact: number
	flags: number
	versionDownloads: number
	totalDownloads: number
	state: number
	shortDesc: string
	icon: string | null
	categories: number[]
	keywords: string[]
	perms: number[]
	description: ResourceHybrid
	chlog: ResourceHybrid
	license: License

	spoons: string[][] = []
	authors: AuthorList
	hardDependencies: FullReleaseIdentifier[] = []
	softDependencies: FullReleaseIdentifier[] = []
	reviews: PluginReview[] = []
	requirements: Requirement[] = []
	enhancements: Requirement[] = []

	lowerStateAlt: {releaseId: number, version: string, state: number} | null = null

	private static createQuery(): db.SelectQuery{
		const query = new db.SelectQuery
		query.fields = {
			repoId: "repos.repoId",
			repoOwner: "repos.owner",
			repoName: "repos.name",
			projectId: "releases.projectId",
			projectName: "projects.name",
			buildPath: "builds.path",
			buildId: "releases.buildId",
			buildClass: "builds.class",
			buildNumber: "builds.internal",
			buildBranch: "builds.branch",
			buildSha: "builds.sha",
			buildMain: "builds.main",

			name: "releases.name",
			releaseId: "releases.releaseId",
			version: "releases.version",
			submitDate: "releases.creation",
			approveDate: "releases.updateTime",
			artifact: "releases.artifact",
			flags: "releases.flags",
			versionDownloads: "SELECT SUM(dlCount) FROM resources WHERE resources.resourceId = releases.artifact",
			totalDownloads: ("SELECT SUM(dlCount) FROM builds " +
				"INNER JOIN resources ON resources.resourceId = builds.resourceId " +
				"WHERE builds.projectId = releases.projectId"),
			state: "releases.state",
			shortDesc: "releases.shortDesc",
			icon: "releases.icon",
			categories: "SELECT GROUP_CONCAT(DISTINCT category ORDER BY isMainCategory DESC SEPARATOR ',') FROM release_categories WHERE release_categories.projectId = releases.projectId",
			keywords: "SELECT GROUP_CONCAT(DISTINCT word SEPARATOR ' ') FROM release_keywords WHERE release_keywords.projectId = releases.projectId",
			perms: "SELECT GROUP_CONCAT(DISTINCT val SEPARATOR ',') FROM release_perms WHERE release_perms.releaseId = releases.releaseId",
			descRsr: "desc.resourceId",
			descType: "desc.type",
			chlogRsr: "chlog.resourceId",
			chlogType: "chlog.type",
			licenseType: "releases.license",
			licenseRsr: "releases.licenseRes",
			licenseRsrType: "lic.type",
		}
		query.from = "releases"
		query.joins = [
			db.Join.ON("INNER", "builds", "buildId", "releases", "buildId"),
			db.Join.ON("INNER", "projects", "projectId", "releases", "projectId"),
			db.Join.ON("INNER", "repos", "repoId", "projects", "repoId"),
			db.Join.ON("LEFT", "resources", "resourceId", "releases", "description", "desc"),
			db.Join.ON("LEFT", "resources", "resourceId", "releases", "changelog", "chlog"),
			db.Join.ON("LEFT", "resources", "resourceId", "releases", "licenseRes", "lic"),
		]
		return query
	}

	private static fromRow(row: any): DetailedRelease{
		const release = new DetailedRelease()
		release.build = {
			repoId: row.repoId,
			repoOwner: row.repoOwner,
			repoName: row.repoName,
			projectId: row.projectId,
			projectName: row.projectName,
			buildPath: row.buildPath,
			buildId: row.buildId,
			buildClass: row.buildClass,
			buildNumber: row.buildNumber,
			branch: row.buildBranch,
			sha: row.buildSha,
			main: row.buildMain,
		}
		release.name = row.name
		release.releaseId = row.releaseId
		release.version = row.version
		release.submitDate = row.submitDate
		release.approveDate = row.approveDate
		release.artifact = row.artifact
		release.flags = row.flags
		release.versionDownloads = row.versionDownloads
		release.totalDownloads = row.totalDownloads
		release.state = row.state
		release.shortDesc = row.shortDesc
		release.icon = row.icon
		release.categories = row.categories.split(",").map(Number)
		release.keywords = row.keywords.split(" ")
		release.perms = row.perms.split(",").map(Number)

		release.description = new ResourceHybrid(row.descRsr, row.descType)
		release.chlog = new ResourceHybrid(row.descRsr, row.descType)
		release.license = new License(row.licenseType, row.licenseRsr, row.licenseRsrType)
		return release
	}

	static fromConstraint(queryManipulator: (query: db.SelectQuery) => void, consumer: (releases: DetailedRelease[]) => void, onError: ErrorHandler){
		const query = this.createQuery()
		queryManipulator(query)
		query.execute((result) =>{
			const releases = result.map(this.fromRow)
			const releaseIdMap: StringMap<DetailedRelease> = {}
			for(let i = 0; i < releases.length; ++i){
				releaseIdMap[releases[i].releaseId] = releases[i]
			}
			const projectIdMap: StringMap<DetailedRelease[]> = {}
			for(let i = 0; i < releases.length; ++i){
				const projectId = releases[i].build.projectId
				if(projectIdMap[projectId] === undefined){
					projectIdMap[projectId] = []
				}
				projectIdMap[projectId].push(releases[i])
			}
			const releaseIds = releases.map(row => row.releaseId)
			const projectIds = Object.keys(projectIdMap).map(Number)
			util.waitAll([
				(complete) =>{
					const query = new db.SelectQuery()
					query.fields = {
						projectId: "projectId",
						uid: "uid",
						name: "name",
						level: "level",
					}
					query.from = "release_authors"
					query.where = query.whereArgs = new ListWhereClause("projectId", projectIds)
					query.execute((result: ResultSet<{
						projectId: number
						uid: number
						name: string
						level: number
					}>) =>{
						const authorLists: StringMap<AuthorList> = {}
						for(let i = 0; i < result.length; ++i){
							let list = authorLists[result[i].projectId]
							if(list === undefined){
								list = authorLists[result[i].projectId] = new AuthorList()
							}
							list.add(result[i].level, result[i].uid, result[i].name)
						}
						for(const projectId in authorLists){
							projectIdMap[projectId].forEach((release) =>{
								release.authors = authorLists[projectId]
							})
						}
						complete()
					}, onError)
				}, // authors
				(complete) =>{
					const query = new db.SelectQuery()
					query.fields = {
						dependentId: "releaseId",
						name: "name",
						version: "version",
						dependencyId: "depRelId",
						required: "isHard",
					}
					query.from = "release_deps"
					query.where = query.whereArgs = new ListWhereClause("releaseId", releaseIds)
					query.execute((result: ResultSet<{
						dependentId: number
						name: string
						version: string
						dependencyId: number
						required: boolean
					}>) =>{
						for(const row of result){
							const release = releaseIdMap[row.dependentId]
							const array = row.required ? release.hardDependencies : release.softDependencies
							array.push({
								name: row.name,
								version: row.version,
								releaseId: row.dependencyId,
							})
						}
						complete()
					}, onError)
				}, // deps
				(complete) =>{
					const query = new db.SelectQuery()
					query.fields = {
						releaseId: "releaseId",
						type: "type",
						details: "details",
						required: "isRequire",
					}
					query.from = "release_reqr"
					query.where = query.whereArgs = new ListWhereClause("releaseId", releaseIds)
					query.execute((result: ResultSet<{
						releaseId: number
						type: number
						details: string
						required: boolean
					}>) =>{
						for(const row of result){
							const release = releaseIdMap[row.releaseId]
							const array = row.required ? release.requirements : release.enhancements
							array.push({
								type: row.type,
								details: row.details,
								isRequire: row.required,
							})
						}
						complete()
					}, onError)
				}, // requirements
				(complete) =>{
					PluginReview.fromConstraint((query) =>{
						query.where = query.whereArgs = new ListWhereClause("release_reviews.releaseId", releaseIds)
					}, reviews =>{
						for(const review of reviews){
							releaseIdMap[review.releaseId].reviews.push(review)
						}
						complete()
					}, onError)
				}, // reviews
				(complete) =>{
					const query = new db.SelectQuery()
					query.fields = {
						releaseId: "releaseId",
						since: "since",
						till: "till",
					}
					query.from = "release_spoons"
					query.where = query.whereArgs = new ListWhereClause("releaseId", releaseIds)
					query.execute((result: ResultSet<{
						releaseId: number
						since: string
						till: string
					}>) =>{
						for(let i = 0; i < result.length; ++i){
							releaseIdMap[result[i].releaseId].spoons.push([result[i].since, result[i].till])
						}
						complete()
					}, onError)
				}, // spoons
			], () => consumer(releases))
		}, onError)
	}
}

class ResourceHybrid{
	resourceId: number
	type: string

	constructor(resourceId: number, type: string){
		this.resourceId = resourceId
		this.type = type
	}
}

class License{
	type: string
	resourceId: number
	resourceType: string

	constructor(type: string, resourceId: number, resourceType: string){
		this.type = type
		this.resourceId = resourceId
		this.resourceType = resourceType
	}
}

class AuthorList{
	private data: StringMap<{uid: number, name: string}[]> = {}

	constructor(){
		for(const level in Release.Author){
			if(!isNaN(Number(level))){
				this.data[level] = []
			}
		}
	}

	add(level: number, uid: number, name: string): void{
		this.data[level].push({uid: uid, name: name})
	}
}

interface Requirement{
	type: number
	details: string
	isRequire: boolean
}
