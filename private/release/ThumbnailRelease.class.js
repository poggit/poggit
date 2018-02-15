"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
var db_1 = require("../db");
var util_1 = require("../util");
var ListWhereClause = db_1.db.ListWhereClause;
var ThumbnailRelease = (function () {
    function ThumbnailRelease() {
        this.spoons = [];
    }
    ThumbnailRelease.fromRow = function (row) {
        var release = new ThumbnailRelease();
        Object.assign(release, row);
        return release;
    };
    ThumbnailRelease.fromConstraint = function (queryManipulator, consumer, onError) {
        var _this = this;
        var query = new db_1.db.SelectQuery;
        query.fields = this.initialFields();
        query.from = "releases";
        query.joins = [
            db_1.db.Join.ON("INNER", "projects", "projectId", "releases", "projectId"),
            db_1.db.Join.ON("INNER", "repos", "repoId", "projects", "repoId"),
        ];
        queryManipulator(query);
        query.execute(function (result) {
            var releases = result.map(function (row) {
                row.categories = row.categories.split(",").map(Number);
                return _this.fromRow(row);
            });
            var releaseIdMap = {};
            for (var i = 0; i < releases.length; ++i) {
                releaseIdMap[releases[i].releaseId] = releases[i];
            }
            var releaseIds = releases.map(function (row) { return row.releaseId; });
            util_1.util.waitAll([
                function (complete) {
                    var query = new db_1.db.SelectQuery();
                    query.fields = {
                        releaseId: "releaseId",
                        since: "since",
                        till: "till",
                    };
                    query.from = "release_spoons";
                    query.where = query.whereArgs = new ListWhereClause("releaseId", releaseIds);
                    query.execute(function (result) {
                        for (var i = 0; i < result.length; ++i) {
                            releaseIdMap[result[i].releaseId].spoons.push([result[i].since, result[i].till]);
                        }
                        complete();
                    }, onError);
                },
            ], function () { return consumer(releases); });
        }, onError);
    };
    ThumbnailRelease.initialFields = function () {
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
        };
    };
    return ThumbnailRelease;
}());
exports.ThumbnailRelease = ThumbnailRelease;
