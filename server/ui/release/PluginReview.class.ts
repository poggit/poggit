import {db} from "../../db"
import {util} from "../../util"
import ListWhereClause = db.ListWhereClause

export class PluginReview{
	releaseId: number
	targetVersion: string
	reviewId: number
	user: number
	userName: string
	criteria: number
	type: number
	cat: number
	score: number
	message: string
	created: Date

	replies: PluginReviewReply[] = []

	private static createQuery(): db.SelectQuery{
		const query = new db.SelectQuery()
		query.fields = {
			releaseId: "release_reviews.releaseId",
			targetVersion: "releases.version",
			reviewId: "release_reviews.reviewId",
			user: "release_reviews.user",
			userName: "users.name",
			criteria: "release_reviews.criteria",
			type: "release_reviews.type",
			cat: "release_reviews.cat",
			score: "release_reviews.score",
			message: "release_reviews.message",
			created: "release_reviews.created",
		}
		query.from = "release_reviews"
		query.joins.push(db.Join.ON("INNER", "users", "uid", "release_reviews", "user"))
		query.joins.push(db.Join.ON("INNER", "releases", "releaseId", "release_reviews"))
		return query
	}

	private static fromRow(row: any): PluginReview{
		const review = new PluginReview()
		Object.assign(review, row)
		return review
	}

	static fromConstraint(queryManipulator: (query: db.SelectQuery) => void, consumer: (reviews: PluginReview[]) => void, onError: ErrorHandler){
		const query = this.createQuery()
		queryManipulator(query)
		query.execute((result) =>{
			const reviews = result.map(this.fromRow)
			const reviewIdMap = {} as StringMap<PluginReview>
			for(const review of reviews){
				reviewIdMap[review.reviewId] = review
			}

			util.waitAll([
				(complete) =>{
					const query = new db.SelectQuery()
					query.fields = {
						reviewId: "release_reply_reviews.reviewId",

						user: "release_reply_reviews.user",
						userName: "users.name",
						message: "release_reply_reviews.message",
						created: "release_reply_reviews.created",
					}
					query.from = "release_reply_reviews"
					query.joins = [db.Join.ON("INNER", "users", "uid", "release_reply_reviews", "user")]
					query.where = query.whereArgs = new ListWhereClause("reviewId", Object.keys(reviewIdMap).map(Number))
					query.execute((result) =>{
						for(const row of result){
							reviewIdMap[row.reviewId as number].replies.push(row as any)
						}
						complete()
					}, onError)
				},
			], () => consumer(reviews))
		}, onError)
	}
}

export interface PluginReviewReply{
	reviewId: number
	user: number
	userName: string
	message: string
	created: Date
}
