"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
var db_1 = require("../../../db");
var util_1 = require("../../../util");
var select_1 = require("../../../db/select");
var ListWhereClause = select_1.dbSelect.ListWhereClause;
var PreviousRelease = (function () {
    function PreviousRelease() {
        this.spoons = [];
    }
    PreviousRelease.fromConstraint = function (queryManipulator, consumer, onError) {
        var query = new db_1.db.SelectQuery;
        query.fields = {
            releaseId: "releases.releaseId",
            version: "releases.version",
            submitDate: "releases.creation",
            approveDate: "releases.updateTime",
            sha: "builds.sha",
            flags: "releases.flags",
            state: "releases.state",
        };
        query.from = "releases";
        query.joins = [
            db_1.db.Join.ON("INNER", "builds", "buildId", "releases", "buildId"),
            db_1.db.Join.ON("INNER", "projects", "projectId", "releases", "projectId"),
            db_1.db.Join.ON("INNER", "repos", "repoId", "projects", "repoId"),
        ];
        queryManipulator(query);
        query.execute(function (result) {
            var releases = result.map(function (row) {
                var release = new PreviousRelease();
                Object.assign(release, row);
                return release;
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
    return PreviousRelease;
}());
exports.PreviousRelease = PreviousRelease;
