"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
var db_1 = require("../db");
var util_1 = require("../util");
var release_1 = require("../consts/release");
var ListWhereClause = db_1.db.ListWhereClause;
var DetailedRelease = (function () {
    function DetailedRelease() {
        this.spoons = [];
        this.hardDependencies = [];
        this.softDependencies = [];
        this.requirements = [];
        this.enhancements = [];
        this.lowerStateAlt = null;
    }
    DetailedRelease.createQuery = function () {
        var query = new db_1.db.SelectQuery;
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
        };
        query.from = "releases";
        query.joins = [
            db_1.db.Join.ON("INNER", "builds", "buildId", "releases", "buildId"),
            db_1.db.Join.ON("INNER", "projects", "projectId", "releases", "projectId"),
            db_1.db.Join.ON("INNER", "repos", "repoId", "projects", "repoId"),
            db_1.db.Join.ON("LEFT", "resources", "resourceId", "releases", "description", "desc"),
            db_1.db.Join.ON("LEFT", "resources", "resourceId", "releases", "changelog", "chlog"),
            db_1.db.Join.ON("LEFT", "resources", "resourceId", "releases", "licenseRes", "lic"),
        ];
        return query;
    };
    DetailedRelease.fromRow = function (row) {
        var release = new DetailedRelease();
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
        };
        release.name = row.name;
        release.releaseId = row.releaseId;
        release.version = row.version;
        release.submitDate = row.submitDate;
        release.approveDate = row.approveDate;
        release.artifact = row.artifact;
        release.flags = row.flags;
        release.versionDownloads = row.versionDownloads;
        release.totalDownloads = row.totalDownloads;
        release.state = row.state;
        release.shortDesc = row.shortDesc;
        release.icon = row.icon;
        release.categories = row.categories.split(",").map(Number);
        release.keywords = row.keywords.split(" ");
        release.perms = row.perms.split(",").map(Number);
        release.description = ResourceHybrid.create(row.descRsr, row.descType);
        release.chlog = ResourceHybrid.create(row.descRsr, row.descType);
        release.license = new License(row.licenseType, row.licenseRsr, row.licenseRsrType);
        return release;
    };
    DetailedRelease.fromConstraint = function (queryManipulator, consumer, onError) {
        var _this = this;
        var query = this.createQuery();
        queryManipulator(query);
        query.execute(function (result) {
            var releases = result.map(_this.fromRow);
            var releaseIdMap = {};
            for (var i = 0; i < releases.length; ++i) {
                releaseIdMap[releases[i].releaseId] = releases[i];
            }
            var projectIdMap = {};
            for (var i = 0; i < releases.length; ++i) {
                var projectId = releases[i].build.projectId;
                if (projectIdMap[projectId] === undefined) {
                    projectIdMap[projectId] = [];
                }
                projectIdMap[projectId].push(releases[i]);
            }
            var releaseIds = releases.map(function (row) { return row.releaseId; });
            var projectIds = Object.keys(projectIdMap).map(Number);
            util_1.util.waitAll([
                function (complete) {
                    var query = new db_1.db.SelectQuery();
                    query.fields = {
                        projectId: "projectId",
                        uid: "uid",
                        name: "name",
                        level: "level",
                    };
                    query.from = "release_authors";
                    query.where = query.whereArgs = new ListWhereClause("projectId", projectIds);
                    query.execute(function (result) {
                        var authorLists = {};
                        for (var i = 0; i < result.length; ++i) {
                            var list = authorLists[result[i].projectId];
                            if (list === undefined) {
                                list = authorLists[result[i].projectId] = new AuthorList();
                            }
                            list.add(result[i].level, result[i].uid, result[i].name);
                        }
                        var _loop_1 = function (projectId) {
                            projectIdMap[projectId].forEach(function (release) {
                                release.authors = authorLists[projectId];
                            });
                        };
                        for (var projectId in authorLists) {
                            _loop_1(projectId);
                        }
                        complete();
                    }, onError);
                },
                function (complete) {
                    var query = new db_1.db.SelectQuery();
                    query.fields = {
                        dependentId: "releaseId",
                        name: "name",
                        version: "version",
                        dependencyId: "depRelId",
                        required: "isHard",
                    };
                    query.from = "release_deps";
                    query.where = query.whereArgs = new ListWhereClause("releaseId", releaseIds);
                    query.execute(function (result) {
                        for (var _i = 0, result_1 = result; _i < result_1.length; _i++) {
                            var row = result_1[_i];
                            var release = releaseIdMap[row.dependentId];
                            var array = row.required ? release.hardDependencies : release.softDependencies;
                            array.push({
                                name: row.name,
                                version: row.version,
                                releaseId: row.dependencyId,
                            });
                        }
                        complete();
                    }, onError);
                },
                function (complete) {
                    var query = new db_1.db.SelectQuery();
                    query.fields = {
                        releaseId: "releaseId",
                        type: "type",
                        details: "details",
                        required: "isRequire",
                    };
                    query.from = "release_reqr";
                    query.where = query.whereArgs = new ListWhereClause("releaseId", releaseIds);
                    query.execute(function (result) {
                        for (var _i = 0, result_2 = result; _i < result_2.length; _i++) {
                            var row = result_2[_i];
                            var release = releaseIdMap[row.releaseId];
                            var array = row.required ? release.requirements : release.enhancements;
                            array.push({
                                type: row.type,
                                details: row.details,
                                isRequire: row.required,
                            });
                        }
                        complete();
                    }, onError);
                },
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
    return DetailedRelease;
}());
exports.DetailedRelease = DetailedRelease;
var ResourceHybrid = (function () {
    function ResourceHybrid() {
    }
    ResourceHybrid.create = function (resourceId, type) {
        if (resourceId === null) {
            return null;
        }
        var hybrid = new ResourceHybrid();
        hybrid.resourceId = resourceId;
        hybrid.type = type;
        return hybrid;
    };
    return ResourceHybrid;
}());
var License = (function () {
    function License(type, resourceId, resourceType) {
        this.type = type;
        this.resourceId = resourceId;
        this.resourceType = resourceType;
    }
    return License;
}());
var AuthorList = (function () {
    function AuthorList() {
        this.data = {};
        for (var level in release_1.Release.Author) {
            if (!isNaN(Number(level))) {
                this.data[level] = [];
            }
        }
    }
    AuthorList.prototype.add = function (level, uid, name) {
        this.data[level].push({ uid: uid, name: name });
    };
    return AuthorList;
}());
