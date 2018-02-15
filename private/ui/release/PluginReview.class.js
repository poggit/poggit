"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
var db_1 = require("../../db");
var util_1 = require("../../util");
var ListWhereClause = db_1.db.ListWhereClause;
var PluginReview = (function () {
    function PluginReview() {
        this.replies = [];
    }
    PluginReview.createQuery = function () {
        var query = new db_1.db.SelectQuery();
        query.fields = {
            releaseId: "release_reviews.releaseId",
            reviewId: "release_reviews.reviewId",
            user: "release_reviews.user",
            userName: "users.name",
            criteria: "release_reviews.criteria",
            type: "release_reviews.type",
            cat: "release_reviews.cat",
            score: "release_reviews.score",
            message: "release_reviews.message",
            created: "release_reviews.created",
        };
        query.from = "release_reviews";
        query.joins.push(db_1.db.Join.ON("INNER", "users", "uid", "release_reviews", "user"));
        return query;
    };
    PluginReview.fromRow = function (row) {
        var review = new PluginReview();
        Object.assign(review, row);
        return review;
    };
    PluginReview.fromConstraint = function (queryManipulator, consumer, onError) {
        var _this = this;
        var query = this.createQuery();
        queryManipulator(query);
        query.execute(function (result) {
            var reviews = result.map(_this.fromRow);
            var reviewIdMap = {};
            for (var _i = 0, reviews_1 = reviews; _i < reviews_1.length; _i++) {
                var review = reviews_1[_i];
                reviewIdMap[review.reviewId] = review;
            }
            util_1.util.waitAll([
                function (complete) {
                    var query = new db_1.db.SelectQuery();
                    query.fields = {
                        reviewId: "release_reply_reviews.reviewId",
                        user: "release_reply_reviews.user",
                        userName: "users.name",
                        message: "release_reply_reviews.message",
                        created: "release_reply_reviews.created",
                    };
                    query.from = "release_reply_reviews";
                    query.joins = [db_1.db.Join.ON("INNER", "users", "uid", "release_reply_reviews", "user")];
                    query.where = query.whereArgs = new ListWhereClause("reviewId", Object.keys(reviewIdMap).map(Number));
                    query.execute(function (result) {
                        for (var _i = 0, result_1 = result; _i < result_1.length; _i++) {
                            var row = result_1[_i];
                            reviewIdMap[row.reviewId].replies.push(row);
                        }
                        complete();
                    }, onError);
                },
            ], function () { return consumer(reviews); });
        }, onError);
    };
    return PluginReview;
}());
exports.PluginReview = PluginReview;
