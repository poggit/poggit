import {db} from "../../../db"
import {Release} from "../../../consts/release"
import {util} from "../../../util"
import {dbSelect} from "../../../db/select"
import {dbTypes} from "../../../db/types"
import ListWhereClause = dbSelect.ListWhereClause
import ResultSet = dbTypes.ResultSet

export class PreviousRelease{
	releaseId: number
	version: string
	submitDate: Date
	approveDate: Date
	sha: string
	flags: number
	state: number & keyof Release.State
	spoons: string[][] = []

	static fromConstraint(queryManipulator: (query: db.SelectQuery) => void, consumer: (releases: PreviousRelease[]) => void, onError: ErrorHandler){
		const query = new db.SelectQuery
		query.fields = {
			releaseId: "releases.releaseId",
			version: "releases.version",
			submitDate: "releases.creation",
			approveDate: "releases.updateTime",
			sha: "builds.sha",
			flags: "releases.flags",
			state: "releases.state",
		}
		query.from = "releases"
		query.joins = [
			db.Join.ON("INNER", "builds", "buildId", "releases", "buildId"),
			db.Join.ON("INNER", "projects", "projectId", "releases", "projectId"),
			db.Join.ON("INNER", "repos", "repoId", "projects", "repoId"),
		]
		queryManipulator(query)
		query.execute((result) =>{
			const releases = result.map((row) =>{
				const release = new PreviousRelease()
				Object.assign(release, row)
				return release
			})
			const releaseIdMap: StringMap<PreviousRelease> = {}
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
}
