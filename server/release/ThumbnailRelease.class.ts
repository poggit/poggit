import {db} from "../db"
import {Release} from "../consts/release"
import {util} from "../util/util"
import ListWhereClause = db.ListWhereClause
import ResultSet = db.types.ResultSet

export class ThumbnailRelease implements ThumbnailReleaseRow{
	releaseId: number
	projectId: number
	name: string
	version: string
	owner: string
	submitDate: Date
	approveDate: Date
	flags: number
	versionDownloads: number
	totalDownloads: number
	reviewCount: number
	reviewMean: number
	state: number & keyof Release.State
	shortDesc: string
	icon: string | null
	categories: number[]
	spoons: string[][] = []

	private static fromRow(row: ThumbnailReleaseRow): ThumbnailRelease{
		const release = new ThumbnailRelease()
		Object.assign(release, row)
		return release
	}

	static fromConstraint(queryManipulator: (query: db.SelectQuery) => void, consumer: (releases: ThumbnailRelease[]) => void, onError: ErrorHandler){
		const query = new db.SelectQuery
		query.fields = this.initialFields()
		query.from = "releases"
		query.joins = [
			db.Join.INNER_ON("projects", "projectId", "releases", "projectId"),
			db.Join.INNER_ON("repos", "repoId", "projects", "repoId"),
		]
		queryManipulator(query)
		query.execute((result) =>{
			const releases = result.map((row: any) =>{
				row.categories = row.categories.split(",").map((i: string) => parseInt(i))
				return this.fromRow(row as ThumbnailReleaseRow)
			})
			const releaseIdMap: StringMap<ThumbnailRelease> = {}
			for(let i = 0; i < releases.length; ++i){
				releaseIdMap[releases[i].releaseId] = releases[i]
			}
			const releaseIds = releases.map(row => row.releaseId)
			util.waitAll([
				(complete) =>{
					const query = new db.SelectQuery()
					query.fields = {
						releaseId: "releaseId",
						since: "since",
						till: "till",
					}
					query.from = "release_spoons"
					query.where = query.whereArgs = new ListWhereClause("releaseId", releaseIds)
					query.execute((result: ResultSet<{releaseId: number, since: string, till: string}>) =>{
						for(let i = 0; i < result.length; ++i){
							releaseIdMap[result[i].releaseId].spoons.push([result[i].since, result[i].till])
						}
						complete()
					}, onError)
				}, // spoons
			], () => consumer(releases))
		}, onError)
	}

	private static initialFields(){
		return {
			releaseId: "releases.releaseId",
			projectId: "releases.projectId",
			name: "releases.name",
			version: "releases.version",
			owner: "repos.owner",
			submitDate: "releases.creation",
			approveDate: "releases.updateTime",
			flags: "releases.flags",
			versionDownloads: "SELECT SUM(dlCount) FROM resources WHERE resources.resourceId = releases.artifact",
			totalDownloads: ("SELECT SUM(dlCount) FROM builds " +
				"INNER JOIN resources ON resources.resourceId = builds.resourceId " +
				"WHERE builds.projectId = releases.projectId"),
			reviewCount: "SELECT COUNT(*) FROM release_reviews WHERE release_reviews.releaseId = releases.releaseId",
			reviewMean: "SELECT IFNULL(AVG(score), 0) FROM release_reviews WHERE release_reviews.releaseId = releases.releaseId",
			state: "releases.state",
			shortDesc: "releases.shortDesc",
			icon: "releases.icon",
			categories: "SELECT GROUP_CONCAT(DISTINCT category ORDER BY isMainCategory DESC SEPARATOR ',') FROM release_categories WHERE release_categories.projectId = releases.projectId",
		}
	}
}

export interface ThumbnailReleaseRow{
	releaseId: number
	projectId: number
	name: string
	version: string
	owner: string
	submitDate: Date
	approveDate: Date
	flags: number
	versionDownloads: number
	totalDownloads: number
	reviewCount: number
	reviewMean: number
	state: number & keyof Release.State
	shortDesc: string
	icon: string | null
	categories: number[]
}
